# API Specification

> Base URL: `https://api.<domain>/api/v1` (local: `http://localhost:8000/api/v1`). JSON only.
> Resources are described by shape here; exact field lists are generated into the frontend `types/` from Laravel API Resources during each phase.

## 1. Conventions

### 1.1 Versioning
- Path versioning: `/api/v1/…`. Breaking changes → `/api/v2` with v1 kept for ≥ 6 months; responses from deprecated versions carry `Deprecation` and `Sunset` headers.
- Non-breaking additions (new fields, new endpoints, new optional params) ship in v1.
- Webhooks are unversioned: `/api/webhooks/payment/{provider}` (§30) — the provider's payload format is the contract.

### 1.2 Response envelope (§69)
Success:
```json
{ "success": true, "data": { }, "message": null, "meta": { } }
```
List (paginated):
```json
{ "success": true, "data": [ ],
  "message": null,
  "meta": { "pagination": { "page": 1, "per_page": 24, "total": 312, "last_page": 13 } } }
```
Error:
```json
{ "success": false, "message": "Validation failed",
  "errors": { "email": ["The email field is required."] },
  "meta": { "request_id": "01J…" } }
```
| HTTP | When | `message` |
|---|---|---|
| 400 | malformed request / idempotency key reused with different body | specific |
| 401 | not authenticated | "Unauthenticated." |
| 403 | authenticated, lacks permission / 2FA required (`errors.code = "two_factor_required"`) | "This action is unauthorized." |
| 404 | not found **or not owned** (never reveal existence of others' resources) | "Not found." |
| 409 | state conflict (e.g. order already paid, stock changed) | specific, `errors.code` |
| 419 | CSRF token mismatch | "Session expired. Please refresh." |
| 422 | validation | "Validation failed" |
| 429 | rate limited (+ `Retry-After`) | "Too many requests." |
| 500 | unhandled | "Something went wrong. Please try again." |

Machine-readable codes go in `errors.code` (e.g. `out_of_stock`, `coupon_expired`, `price_changed`, `two_factor_required`).

### 1.3 Identifiers
- Never expose bigint ids. Products/categories/brands/pages by `slug` (public) and `uuid` (admin); orders by `order_number`; tickets by `ticket_number`; everything else by `uuid`.
- Money fields are objects: `{"amount": 249900, "currency": "INR", "formatted": "₹2,499.00"}` (`formatted` is a convenience from the server locale; the frontend may re-format).

### 1.4 Headers
| Header | Direction | Purpose |
|---|---|---|
| `X-Request-ID` | both | Correlation id (§87); generated if absent. |
| `X-XSRF-TOKEN` | request | CSRF for cookie-authenticated mutations. |
| `Idempotency-Key` | request | **Required** on `POST /checkout/place-order`, `POST /orders/{n}/payment/confirm`, admin refund. UUID; stored 24 h in `idempotency_keys`; same key + same body → replay stored response; same key + different body → 400. |
| `X-Cart-Token` | — | Not used; guest cart token travels in httpOnly cookie `cart_token`. |
| `Accept-Language` | request | Future i18n; ignored in v1. |

### 1.5 Pagination, filtering, sorting
- `?page=2&per_page=24` (max 100; admin max 200).
- Filters: `?filter[category]=electronics&filter[brand]=apple,samsung&filter[price_min]=100000&filter[price_max]=500000&filter[type]=digital&filter[in_stock]=1&filter[on_sale]=1&filter[rating_min]=4&filter[attr][color]=black,blue&filter[attr][size]=m`.
- Sort: `?sort=relevance|newest|price_asc|price_desc|popularity|rating|best_selling|discount` (storefront). Admin: `?sort=-created_at` style on whitelisted columns.
- Unknown filter/sort keys → 422 (no silent ignore, no dynamic column names → no SQL injection surface).

### 1.6 Rate limits (per IP unless noted)
| Limiter | Limit |
|---|---|
| `public` (catalog, search) | 120/min |
| `search-suggest` | 60/min |
| `auth` (login, register, forgot/reset password, 2FA verify) | 5/min per email+IP, 20/min per IP; progressive lockout after 10 failures/hour |
| `cart` | 60/min per cart token |
| `checkout` | 10/min per cart token + user |
| `downloads` | 20/min per user/entitlement |
| `account` | 60/min per user |
| `admin` | 300/min per user |
| `webhooks` | 600/min per provider (not IP-limited for provider ranges) |

## 2. Authentication flow (Sanctum SPA)
1. `GET /sanctum/csrf-cookie` (outside `/api/v1`) → sets `XSRF-TOKEN`.
2. `POST /api/v1/auth/login` `{email, password, remember}` → 200 user, or 200 `{two_factor_required: true}` (session holds pending login) → `POST /api/v1/auth/two-factor/challenge` `{code | recovery_code}`.
3. Session cookie (`Domain=.<domain>`) authenticates subsequent requests from `www.<domain>`.
4. `POST /api/v1/auth/logout`; `POST /api/v1/account/sessions/logout-others` (password confirm).
5. On login/register, server merges the guest cart (cookie) into the user cart.
6. Email verification: the emailed link is a signed `GET /api/v1/auth/email/verify/{uuid}/{hash}` that verifies and redirects to `{FRONTEND_URL}/account?verified=1`. Password reset emails link to `{FRONTEND_URL}/reset-password?token=…&email=…`.

## 3. Endpoint catalog

Legend: **Auth** — `public`, `guest|user` (guest allowed, user optional), `user` (logged-in customer or staff), `perm:x` (staff with permission x, 2FA enforced). **Ph** — implementation phase (ROADMAP.md).

### 3.1 System
| Method | Path | Auth | Ph | Notes |
|---|---|---|---|---|
| GET | `/health` | public | 1 | `{status, db, queue_lag_seconds, version}` — db/queue details only with internal token |
| GET | `/settings/public` | public | 1 | store name, currency, locale, enabled payment methods, feature flags |

### 3.2 Auth & account
| Method | Path | Auth | Ph |
|---|---|---|---|
| POST | `/auth/register` | public | 1 |
| POST | `/auth/login` | public | 1 |
| POST | `/auth/two-factor/challenge` | pending-login session | 1 |
| POST | `/auth/logout` | user | 1 |
| POST | `/auth/forgot-password` | public | 1 |
| POST | `/auth/reset-password` | public | 1 |
| GET | `/auth/email/verify/{uuid}/{hash}` (signed link → redirect to storefront) | public | 1 |
| POST | `/auth/email/resend` | user | 1 |
| GET | `/me` | user | 1 |
| PATCH | `/account/profile` | user | 4 |
| PUT | `/account/password` | user | 1 |
| GET | `/account/sessions` (no session ids exposed) | user | 1 |
| POST | `/account/sessions/logout-others` `{password}` | user | 1 |
| POST | `/account/two-factor` `{password}` → `{secret, otpauth_url, qr_svg}` | user | 1 |
| POST | `/account/two-factor/confirm` `{code}` → `{recovery_codes}` (shown once) | user | 1 |
| DELETE | `/account/two-factor` `{password}` (refused for staff: `two_factor_required_for_staff`) | user | 1 |
| POST | `/account/two-factor/recovery-codes` `{password}` → new codes | user | 1 |
| GET/POST/PATCH/DELETE | `/account/addresses[/{uuid}]` | user | 4 |
| GET | `/account/notifications`, POST `/account/notifications/{id}/read` | user | 7 |

### 3.3 Catalog (public, cacheable)
| Method | Path | Ph | Notes |
|---|---|---|---|
| GET | `/categories` | 3 | tree |
| GET | `/categories/{slug}` | 3 | category + breadcrumbs + child categories + available facets |
| GET | `/brands`, `/brands/{slug}` | 3 | |
| GET | `/products` | 3 | list; filters/sort §1.5; `filter[category]`, `filter[q]` |
| GET | `/products/{slug}` | 3 | detail: variants, media, specs, price range, stock state (`in_stock|low_stock|out_of_stock`, never exact counts), rating summary, SEO, JSON-LD data |
| GET | `/products/{slug}/related` | 3 | related/upsell/cross-sell |
| GET | `/products/{slug}/reviews` | 4 | approved reviews, paginated, rating histogram |
| GET | `/search` | 3 | `q` + filters/sort; returns results + `did_you_mean` + facets |
| GET | `/search/suggest?q=` | 3 | autocomplete: products, categories, popular terms |
| GET | `/search/popular` | 3 | popular searches |
| GET | `/home` | 4 | resolved homepage sections (page `home`) |
| GET | `/pages/{slug}`, `/blog`, `/blog/{slug}`, `/faqs`, `/menus/{handle}`, `/banners?placement=` | 4/7 | CMS |
| POST | `/newsletter/subscribe` | 4 | double opt-in email |
| POST | `/events` | 4 | analytics beacon (throttled, batched) |

### 3.4 Cart (guest|user)
| Method | Path | Ph | Notes |
|---|---|---|---|
| GET | `/cart` | 4 | priced cart (server-computed totals), warnings (`price_changed`, `out_of_stock`) |
| POST | `/cart/items` | 4 | `{variant_uuid, quantity}` |
| PATCH | `/cart/items/{uuid}` | 4 | `{quantity}` or `{saved_for_later}` |
| DELETE | `/cart/items/{uuid}` | 4 | |
| POST/DELETE | `/cart/coupon` | 5 | `{code}` |
| POST | `/cart/shipping-estimate` | 5 | `{country_code, state_code, postal_code}` → methods + rates |

*(PRD §68 `POST /api/v1/cart` is realized as `POST /cart/items`.)*

### 3.5 Wishlist (user)
| Method | Path | Ph |
|---|---|---|
| GET | `/wishlist` | 4 |
| POST | `/wishlist/items` `{product_uuid, variant_uuid?, notify_price_drop?, notify_back_in_stock?}` | 4 |
| DELETE | `/wishlist/items/{uuid}` | 4 |
| POST | `/wishlist/items/{uuid}/move-to-cart` | 4 |

### 3.6 Checkout & orders
| Method | Path | Auth | Ph | Notes |
|---|---|---|---|---|
| GET | `/checkout` | guest|user | 5 | checkout state: requires_shipping, contact, addresses, available shipping methods, priced totals, enabled gateways |
| PUT | `/checkout/contact` | guest|user | 5 | `{email, phone?, subscribe?}` |
| PUT | `/checkout/address` | guest|user | 5 | shipping + billing (or `same_as_shipping`) |
| PUT | `/checkout/shipping-method` | guest|user | 5 | `{method_code}` |
| POST | `/checkout/place-order` | guest|user | 5 | **Idempotency-Key**; body `{gateway, expected_total}` — `expected_total` only to detect drift (409 `price_changed` if it differs), never used for charging. Returns `{order_number, access_token (guest only), payment: {gateway, client_payload}}` |
| POST | `/orders/{order_number}/payment/confirm` | owner | 5 | **Idempotency-Key**; provider ids/signature → server verifies with provider; returns current order status |
| POST | `/orders/{order_number}/payment/retry` | owner | 5 | new payment attempt for `pending/failed` orders within reservation window |
| GET | `/orders` | user | 5 | own orders |
| GET | `/orders/{order_number}` | owner | 5 | owner = logged-in user who placed it, or guest with `?access_token=` / `order_access` cookie |
| POST | `/orders/{order_number}/cancel` | owner | 5 | only allowed states |
| POST | `/orders/{order_number}/refund-request` | owner | 7 | creates `refund_requested` + support ticket |
| POST | `/orders/{order_number}/claim` | user | 5 | attach a guest order to a new account (email must match + access token) |

*(PRD §68 `POST /checkout` + `POST /orders` are merged into `POST /checkout/place-order` so an order can never exist without a priced checkout.)*

### 3.7 Digital downloads
| Method | Path | Auth | Ph | Notes |
|---|---|---|---|---|
| GET | `/account/downloads` | user / guest token | 6 | entitlements with files, remaining downloads, expiry |
| POST | `/account/downloads/{entitlement_uuid}/files/{file_uuid}/link` | owner | 6 | returns `{url, expires_at}` (5-min single-use) |
| GET | `/downloads/{token}` | token | 6 | streams file; `404` if invalid/expired/used; logs attempt |

### 3.8 Reviews & support
| Method | Path | Auth | Ph |
|---|---|---|---|
| POST | `/products/{slug}/reviews` (multipart media) | user | 7 |
| PATCH/DELETE | `/reviews/{uuid}` | owner | 7 |
| GET/POST | `/support/tickets` | user | 7 |
| GET | `/support/tickets/{ticket_number}` | owner | 7 |
| POST | `/support/tickets/{ticket_number}/messages` | owner | 7 |
| POST | `/support/contact` | public (captcha-ready, rate-limited) | 7 |

### 3.9 Webhooks
| Method | Path | Auth | Ph |
|---|---|---|---|
| POST | `/api/webhooks/payment/{provider}` | provider signature | 5 |
| GET | `/api/v1/orders/{orderNumber}/invoice` | owner or guest `X-Order-Token` | GST tax invoice PDF; 404 until payment is confirmed |
| POST | `/api/webhooks/delivery-updates` | Shiprocket `x-api-key` token | courier tracking → order stage (deduped in `webhook_events`, never moves an order backwards; RTO/lost flags `requires_attention`) |

Behaviour: ARCHITECTURE §6.11. Always responds `200` for valid signature (including duplicates), `401` invalid signature, `404` unknown/disabled provider. CSRF-exempt.

### 3.10 Admin (`/admin/…`, staff only, 2FA enforced)
All admin endpoints: `auth:sanctum` + `admin.2fa` + per-route permission. Every mutating admin endpoint writes `audit_logs`.

| Area | Endpoints | Permission | Ph |
|---|---|---|---|
| Me | `GET /admin/me` (user + permissions) | any staff | 1 |
| Dashboard | `GET /admin/dashboard?range=today|7d|30d|90d|12m|custom&from&to` | `dashboard.view` | 7 |
| Products | `GET/POST /admin/products`, `GET/PATCH/DELETE /admin/products/{uuid}` (DELETE = archive), `POST /admin/products/{uuid}/publish`, `POST /admin/products/{uuid}/duplicate` | `products.view|create|edit|delete` | 3 |
| Variants | `GET/POST /admin/products/{uuid}/variants`, `PATCH/DELETE /admin/variants/{uuid}`, `POST /admin/products/{uuid}/variants/generate` (from attribute axes) | `products.edit` | 3 |
| Product media | `POST/PATCH/DELETE /admin/products/{uuid}/media[/{id}]`, `PUT /admin/products/{uuid}/media/order` | `products.edit` | 3 |
| Categories / brands / attributes | CRUD `/admin/categories`, `/admin/brands`, `/admin/attributes`, `/admin/attributes/{uuid}/values` | `categories.manage`, `brands.manage`, `attributes.manage` | 3 |
| Inventory | `GET /admin/inventory`, `GET /admin/inventory/{variant_uuid}/transactions`, `POST /admin/inventory/{variant_uuid}/adjust` `{type, quantity, note}` | `inventory.view|edit` | 3 |
| Import/export | `POST /admin/imports` (CSV), `GET /admin/imports/{uuid}`, `POST /admin/imports/{uuid}/confirm`, `POST /admin/exports`, `GET /admin/exports/{uuid}` | `imports.run`, `exports.run` | 7 |
| Orders | `GET /admin/orders`, `GET /admin/orders/{order_number}`, `POST …/status` `{to, reason}`, `POST …/cancel`, `POST …/notes` | `orders.view|edit|cancel` | 7 |
| Refunds | `POST /admin/orders/{order_number}/refunds` (**Idempotency-Key**) | `orders.refund` | 7 |
| Shipments | `POST /admin/orders/{order_number}/shipments`, `PATCH /admin/shipments/{uuid}`, `POST /admin/shipments/{uuid}/tracking-events` | `shipments.manage` | 7 |
| Customers | `GET /admin/customers`, `GET /admin/customers/{uuid}` (+ analytics §74), `PATCH …` (block/unblock, details) | `customers.view|edit` | 7 |
| Digital | `GET/POST /admin/products/{uuid}/files` (chunked upload), `PATCH/DELETE /admin/digital-files/{uuid}`, `GET /admin/downloads` (log), `GET /admin/entitlements`, `POST /admin/entitlements/{uuid}/revoke|restore|reset-count` | `digital.manage`, `downloads.view`, `entitlements.revoke` | 6 |
| Coupons / promotions | CRUD `/admin/coupons`, `/admin/promotions` | `coupons.manage`, `promotions.manage` | 7/8 |
| Reviews | `GET /admin/reviews`, `POST /admin/reviews/{uuid}/approve|reject` | `reviews.moderate` | 7 |
| Support | `GET /admin/tickets`, `GET/PATCH /admin/tickets/{ticket_number}`, `POST …/messages`, `POST …/assign` | `support.view|reply|assign` | 7 |
| Content | CRUD `/admin/pages` (+ `/sections` add/move/hide/edit), `/admin/blog-posts`, `/admin/banners`, `/admin/menus`, `/admin/faqs` | `content.manage` | 7 |
| Media library | `GET/POST /admin/media`, `PATCH/DELETE /admin/media/{uuid}`, `POST /admin/media/{uuid}/replace` | `media.manage` | 7 |
| Marketing | `GET /admin/subscribers`, export | `subscribers.manage` | 8 |
| Reports | `GET /admin/reports/{sales|products|customers|inventory|finance}` | `reports.view` (`reports.finance` for finance) | 7 |
| Staff & roles | **Built:** `GET /admin/staff`, `GET /admin/staff/roles` (roles + what each allows, `assignable`), `POST /admin/staff` (`name,email,role,password`; invites by email, or promotes an existing customer; 201), `PATCH /admin/staff/{uuid}` (`role?`, `is_active?`, `password`), `POST /admin/staff/{uuid}/two-factor-reset` (`password`), `POST /admin/staff/{uuid}/invitation` (resend). Errors: 409 `already_staff`/`account_closed`, 403 `cannot_manage_self`/`super_admin_only`, 404 `not_staff`. Planned: `GET/POST/PATCH /admin/roles` | `users.manage`, `roles.manage` | 7 |
| Settings | `GET/PATCH /admin/settings/{group}` (store, checkout, payments (non-secret), tax, shipping, notifications), CRUD tax classes/rates/rules, shipping zones/methods/rates, carriers | `settings.manage` | 5/7 |
| Audit | `GET /admin/audit-logs` | `audit.view` | 7 |
| POST | `/admin/orders/{order}/status` | `orders.edit` | `to` ∈ processing, packed, out_for_delivery, delivered; walks intermediate stages; cannot pass "shipped" (409 `shipment_required`). Reaching packed books Shiprocket when configured |
| POST | `/admin/orders/{order}/courier-booking` | `shipments.manage` | (Re)try the Shiprocket booking for a packed order; resumes from the last successful step |
| POST | `/admin/settings/shiprocket/test` | `settings.manage` | Logs in to Shiprocket and lists pickup locations |
| GET | `/admin/orders/{order}/invoice` | `orders.view` | GST tax invoice PDF (issued on demand for older paid orders) |
| GET | `/admin/exports/{type}` | `exports.run` + area permission | `.xlsx` download; type ∈ products, inventory, orders, customers, digital, coupons, report (`from`/`to` for orders and report). Report includes only the areas the user may view. Audited as `export.downloaded` |

## 4. Frontend → API mapping (public URLs, §50)
| Page | API |
|---|---|
| `/` | `/home` |
| `/shop` | `/products` |
| `/category/{slug}` | `/categories/{slug}` + `/products?filter[category]=` |
| `/product/{slug}`, `/digital/{slug}` | `/products/{slug}` |
| `/search?q=` | `/search` |
| `/cart` | `/cart` |
| `/checkout` | `/checkout` |
| `/account`, `/orders`, `/orders/{order_number}`, `/downloads`, `/wishlist` | `/me`, `/orders`, `/orders/{n}`, `/account/downloads`, `/wishlist` |

## 5. Contract testing
- Each endpoint has a Pest API test asserting envelope shape, status codes, authorization (owner vs other user vs guest vs staff without permission), and validation errors (TESTING.md).
- Frontend DTO types are kept in sync manually per phase; a later phase may add OpenAPI generation (`dedoc/scramble`) if drift becomes a problem.
