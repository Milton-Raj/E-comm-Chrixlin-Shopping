# Security Model

> Applies to every phase. Phase 9 is a dedicated audit, but each phase must already satisfy this document for the code it adds.

## 1. Assets & trust boundaries

| Asset | Why it matters |
|---|---|
| Payments, refunds, order totals | Direct financial loss |
| Digital files | Paid content; leakage = revenue loss |
| Customer PII (names, emails, phones, addresses) | Legal (India DPDP Act 2023, GDPR for EU customers), trust |
| Admin accounts | Full control of store |
| Inventory | Overselling, fraud |
| Secrets (APP_KEY, gateway keys, webhook secrets, SMTP) | Escalation to all of the above |

Trust boundaries: browser ↔ Vercel (untrusted client), Vercel ↔ Laravel (server-to-server, still treated as untrusted input), payment provider → webhook (trusted only after signature verification), admin browser (trusted only with session + 2FA + permission).

## 2. Threat model (STRIDE-oriented, top threats)

| Threat | Example | Controls |
|---|---|---|
| **Price / total tampering** | Client sends lower price or total | Prices computed only server-side (`CartPricingService`); client `expected_total` is a drift check only; payment amount comes from the order row. |
| **IDOR** | `GET /orders/ORD-2026-000123` for someone else's order; guessing entitlement uuids | Policies on every resource; ownership queries (`where user_id = auth id`) not post-fetch checks; 404 (not 403) for non-owned; non-sequential uuids for everything except order/ticket numbers, which always require ownership. |
| **Webhook spoofing / replay** | Forged `payment.captured` | HMAC signature verification with provider secret (constant-time compare), unique `(provider, event_id)`, amount + currency + order match check before applying, server-to-server status fetch for confirm path. |
| **Browser-redirect payment fraud** | Visiting success URL without paying | Order is `paid` only via webhook or server-side provider status fetch (§29). |
| **Download link sharing** | Customer posts link publicly | Single-use 5-min tokens; per-entitlement limits; logging (IP, UA); admin revocation; tokens bound to an IP-prefix hash (soft, logged mismatch rather than hard fail to avoid mobile-network false positives — decision recorded in Phase 6). |
| **Direct file access** | `https://api.<domain>/storage/…/course.zip` | Digital files on private disk outside web root; `.htaccess` deny on `storage/`; `public` disk holds only catalog media. |
| **Account takeover** | Credential stuffing | Rate limits + progressive lockout, generic error messages, bcrypt/argon2id, optional customer 2FA, password breach check (HIBP k-anonymity) on register/reset, session regeneration on login, login notifications for staff. |
| **Admin takeover** | Phished staff password | TOTP 2FA for staff (on by default; owner-switchable — see §3), 30-min idle admin session timeout, re-auth (password confirm) for sensitive actions (refund, role change, 2FA reset, settings), IP/device logging, audit log. |
| **CSRF** | Cross-site POST using session cookie | Sanctum CSRF (`XSRF-TOKEN`), `SameSite=Lax` cookies, CORS limited to `https://www.<domain>`, `supports_credentials` only for that origin. |
| **XSS** | Script in review/product description/CMS | React escaping by default; CMS/product HTML sanitized server-side on write (HTML Purifier allow-list) and never rendered with unsanitized `dangerouslySetInnerHTML`; strict CSP with nonces; uploaded SVG disallowed (or sanitized); `X-Content-Type-Options: nosniff`. |
| **SQL injection** | Filters/sort params | Eloquent/query builder bindings only; whitelisted sort/filter keys (unknown → 422); no raw interpolation; FULLTEXT query string sanitized of boolean operators before adding `*`. |
| **Malicious uploads** | PHP shell disguised as image; zip bombs | Extension + MIME sniff allow-lists, size limits, images re-encoded, random storage names, no execution in upload dirs (`.htaccess` `php_flag engine off` / LiteSpeed equivalent), digital files never served from public paths. |
| **Mass assignment** | `is_admin=1` in profile update | `$fillable` + FormRequest `validated()` only; roles changed only via dedicated admin endpoint. |
| **Coupon abuse** | Reusing single-use code concurrently | Coupon usage recorded inside the order transaction with row lock on coupon; per-customer limit checked by user id **and** email. |
| **Inventory races** | Two buyers, one item | `SELECT … FOR UPDATE` on inventory rows; reservations; ledger. |
| **Duplicate orders/charges** | Double-click, network retry | `Idempotency-Key` on place-order/confirm/refund; unique constraints on provider ids. |
| **Information disclosure** | Stack traces, enumeration | `APP_DEBUG=false` in prod; generic errors with request id; forgot-password always returns same message; login error doesn't reveal whether email exists. |
| **Log leakage** | Passwords/tokens in logs | Monolog redaction processor; payloads stored redacted; tests assert redaction. |
| **Supply chain** | Malicious package | Minimal deps, lockfiles committed, `composer audit` + `npm audit` in CI, Dependabot/Renovate. |

## 3. Authentication & sessions (§62)
- Passwords: Laravel `Hash` (bcrypt cost ≥ 12 or argon2id), min 10 chars, breached-password check, never logged or returned.
- Email verification required before: writing reviews, opening support tickets from account, enabling 2FA. **Not** required to check out (guest checkout is mandatory, §27).
- Password reset tokens: hashed, 60-min expiry, single use; all other sessions invalidated on reset.
- Sessions: database driver, `Secure`, `HttpOnly`, `SameSite=Lax`, `Domain=.<domain>`, 120-min lifetime (customers, sliding) / 30-min idle for admin routes (`EnsureAdminSessionFresh`). Session id regenerated on login and privilege change.
- "Log out all devices": delete user's `sessions` rows, rotate `remember_token`.
- The staff 2FA requirement is a setting (default on, `ADMIN_REQUIRE_2FA`). **Owner decision 2026-10-09:** it may be switched off in any environment, including production, from Settings → Security; the change needs the current password, is audited (`settings.security_2fa_*`) and emails every active owner/administrator. Accepted risk: with it off, a stolen staff password alone opens the admin.
- Password change from the admin "My account" page is confirmed by a single-use 6-digit code emailed to the account (10-minute expiry, 5 attempts, hashed at rest); success signs out other devices and emails a confirmation.
- 2FA: TOTP (RFC 6238, ±1 window, replay-protected by storing last used timestep), 8 one-time recovery codes (hashed). Staff cannot access admin API until 2FA is confirmed (`two_factor_required` error drives the UI to setup). 2FA reset for staff requires another user with `users.manage` and is audited.
- Staff management (Admin → Staff, `users.manage`): every change needs the actor's current password and is audited (`staff.created|promoted|role_changed|deactivated|reactivated|two_factor_reset|invite_resent`). Nobody changes their own staff access; only a super-admin grants, changes or removes super-admin. New staff never receive a password from the admin: they get an emailed set-password link (standard reset token). Deactivation signs the person out of every session; `EnsureAdminAccess` also refuses inactive users. Staff are never deleted.

## 4. Permission catalog
Seeded by `PermissionSeeder` (idempotent, all environments). Guard: `web` (Sanctum SPA).

| Permission | Description |
|---|---|
| `dashboard.view` | View admin dashboard |
| `products.view` / `products.create` / `products.edit` / `products.delete` | Catalog products & variants (delete = archive) |
| `categories.manage` | Categories |
| `brands.manage` | Brands |
| `attributes.manage` | Attributes & values |
| `reviews.moderate` | Approve/reject reviews |
| `inventory.view` / `inventory.edit` | View stock & ledger / adjust stock |
| `orders.view` / `orders.edit` / `orders.cancel` / `orders.refund` | Orders |
| `shipments.manage` | Create shipments, tracking |
| `customers.view` / `customers.edit` | Customers |
| `digital.manage` | Upload/replace digital files, digital settings |
| `downloads.view` | Download logs |
| `entitlements.revoke` | Revoke/restore/reset entitlements |
| `coupons.manage` | Coupons |
| `promotions.manage` | Promotions |
| `subscribers.manage` | Newsletter subscribers |
| `content.manage` | Pages, sections, blog, banners, menus, FAQs |
| `media.manage` | Media library |
| `support.view` / `support.reply` / `support.assign` | Support tickets |
| `reports.view` / `reports.finance` | Reports / financial reports (revenue, refunds, cost) |
| `imports.run` / `exports.run` | Bulk CSV import / export |
| `users.manage` | Staff accounts, staff 2FA reset |
| `roles.manage` | Roles & role permissions |
| `settings.manage` | Store, checkout, payment (non-secret), tax, shipping settings |
| `audit.view` | Audit log |

### Default role → permission matrix
| Role | Permissions |
|---|---|
| `super-admin` | all (via `Gate::before`; the only role-name check in the codebase) |
| `administrator` | all except `roles.manage` |
| `product-manager` | dashboard.view, products.*, categories.manage, brands.manage, attributes.manage, media.manage, inventory.view, digital.manage, reviews.moderate, imports.run, exports.run |
| `order-manager` | dashboard.view, orders.view, orders.edit, orders.cancel, shipments.manage, customers.view, inventory.view, downloads.view |
| `customer-support` | orders.view, customers.view, support.*, downloads.view, entitlements.revoke |
| `inventory-manager` | dashboard.view, inventory.view, inventory.edit, products.view, imports.run, exports.run |
| `marketing-manager` | dashboard.view, coupons.manage, promotions.manage, subscribers.manage, content.manage, media.manage, reports.view |
| `content-manager` | content.manage, media.manage |
| `finance-manager` | dashboard.view, orders.view, orders.refund, reports.view, reports.finance, exports.run |
| `customer` | none (customer abilities are ownership policies, not permissions) |

Roles and their permissions are editable by `roles.manage` holders; `super-admin` cannot be assigned/removed except by another super-admin (audited).

## 5. Authorization rules
- Every route declares authorization: `can:` middleware or FormRequest `authorize()` calling a Policy. A test (`RouteAuthorizationTest`) enumerates all `/api/v1/admin/*` routes and fails if any lacks a permission middleware.
- Customer resources (orders, addresses, wishlist, entitlements, tickets, reviews) use ownership policies; queries are scoped to the owner.
- Staff permissions never imply customer-ownership bypass on customer endpoints; staff use admin endpoints.

## 6. HTTP security
| Control | Laravel API | Next.js |
|---|---|---|
| HTTPS | Hostinger SSL + HSTS (`max-age=31536000; includeSubDomains`) after verification | Vercel automatic + HSTS |
| CORS | `allowed_origins=[https://www.<domain>]` (+ local), credentials true, methods/headers whitelisted | n/a |
| CSP | API returns JSON: `default-src 'none'; frame-ancestors 'none'` | Static policy (ARCHITECTURE D11): `script-src 'self' 'unsafe-inline'`, `connect-src`/`img-src` self + API origin, payment providers added in Phase 5, `frame-ancestors 'none'`, `object-src 'none'`. Nonce/SRI hardening reviewed in Phase 9. |
| Other headers | `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` minimal | same + `X-Frame-Options: DENY` |
| Cookies | session/XSRF: Secure, SameSite=Lax; session HttpOnly | `cart_token` set by API (HttpOnly) |

## 7. Data protection
- PII minimization: store only what fulfillment, tax, and support need. IP addresses kept 12 months for fraud/security logs, then anonymized.
- Encryption: `two_factor_secret`, recovery codes, license keys, encrypted settings use Laravel encryption (`APP_KEY`). DB backups encrypted at rest off-site.
- Card data never touches our servers (gateway-hosted checkout/SDK) → out of PCI DSS scope beyond SAQ A.
- Customer data export & deletion (account deletion anonymizes orders — financial records retained per law, PII scrubbed).
- Consent recorded for marketing (subscribers.consent_*).

## 8. Secrets
- Only in environment variables (`.env` on Hostinger outside web root, Vercel env settings). `.env*` git-ignored; `.env.example` lists names only.
- Rotation procedure for APP_KEY (with `APP_PREVIOUS_KEYS`), gateway keys, webhook secrets documented in DEPLOYMENT.md.
- `NEXT_PUBLIC_*` variables must never contain secrets (lint check on names).

## 9. Logging & audit (§65, §85)
- Never log: passwords, tokens, session ids, full card data, CVV, OTPs, API keys, webhook secrets, `Authorization`/`Cookie` headers.
- Security events logged to `auth` channel: login success/failure, lockout, 2FA enable/disable/failure, password reset, session revocation, permission denied on admin routes.
- Audited actions (non-exhaustive, all admin mutations included): product/variant/price changes, inventory adjustments, order status changes, cancellations, refunds, shipment changes, entitlement revoke/restore/reset, coupon/promotion changes, role/permission/staff changes, 2FA resets, settings changes, CMS publish, imports/exports, customer edits/blocks.

## 10. Security testing
See TESTING.md §4 — authorization-bypass suite, webhook signature/replay tests, download token abuse tests, rate-limit tests, upload validation tests, log redaction tests, header tests. Phase 9 adds a manual review against OWASP ASVS L2 and `composer audit`/`npm audit`.
