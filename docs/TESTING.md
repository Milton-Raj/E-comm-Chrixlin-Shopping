# Testing Strategy

> Rule (CLAUDE.md): a feature is not complete until its tests exist and pass. Each phase's exit criteria in ROADMAP.md lists the required suites.

## 1. Tooling

| Layer | Tool | Location |
|---|---|---|
| Backend unit / feature / API / security | **Pest** (on PHPUnit), Laravel testing helpers, `Http::fake()`, `Queue::fake()`, `Notification::fake()` | `backend/tests/{Unit,Feature,Api,Security}` |
| Backend static analysis | Larastan (PHPStan) level 6 → raise over time; Laravel Pint | CI |
| Frontend unit / component | **Vitest** + React Testing Library + `@testing-library/user-event`; MSW for API mocking | `frontend/**/*.test.ts(x)` |
| Frontend static | `tsc --noEmit` (strict), ESLint (next + jsx-a11y) | CI |
| End-to-end | **Playwright** (Chromium now; WebKit added in Phase 11) with projects `mobile` (390×844), `tablet` (768×1024), `desktop` (1280×800), plus spot checks at 360 and 1920 later | `tests/e2e/` |
| Accessibility | `@axe-core/playwright` on every E2E page visit (fail on serious/critical); jsx-a11y lint | E2E |
| Performance | Lighthouse CI budgets: LCP < 2.5 s, CLS < 0.1, TBT proxy for INP < 200 ms, JS per route budget | `lighthouse.yml` |

## 2. Test database
- Feature/API tests run against **MySQL** (`ecom_testing`), not SQLite — FULLTEXT, row locking, JSON and strictness must match production. CI runs MySQL 8.0 and MariaDB 10.11.
- `RefreshDatabase` with transactions; concurrency tests (inventory, coupons) use separate connections and real commits.
- Factories for every model; states for common scenarios (`->digital()`, `->withVariants(3)`, `->paid()`, `->guest()`).
- Time-dependent logic uses `$this->travel()` / frozen `Carbon::now()`.
- External services: gateways via `FakeGateway` + recorded webhook fixtures (signed with test secret); `Http::preventStrayRequests()` globally.

## 3. Required coverage by area (§88)

### Unit (pure logic, no DB where possible)
| Area | Cases (minimum) |
|---|---|
| Money | add/subtract/multiply, allocation sums exactly, rounding half-up, currency mismatch throws, formatting per currency exponent |
| Pricing | sale price windows, default variant, mixed physical/digital, price never read from input |
| Discounts / coupons | percentage w/ max cap, fixed, free shipping, min order, first order, targets & exclusions, expiry, usage & per-customer limits, allocation across lines |
| Promotions | category percent, cart threshold, stacking/priority rules |
| Tax | inclusive vs exclusive, intra-state CGST+SGST vs inter-state IGST, zero-rated class, rounding per line, digital-only destination fallback |
| Shipping | zone resolution (country/state/postcode), flat/weight/price/free-over rates, digital-only = no shipping |
| Inventory | ledger sum = on_hand, reservation math, expiry, commit, release, backorder flag |
| Order calculations | totals = Σ lines − discounts + shipping + tax; snapshot immutability |
| State machines | every allowed transition passes, every disallowed transition throws |
| Order number | format, year rollover, sequential under concurrency |

### Feature / API (HTTP-level)
| Area | Cases |
|---|---|
| Registration / login | success, validation, duplicate email, lockout after N failures, 2FA challenge, remember me, logout, logout-others |
| Password reset / email verify | token expiry, single use, sessions invalidated |
| Cart | guest create via cookie, add/update/remove, save for later, stock caps, price-change warning, **guest→user merge** (idempotent) |
| Checkout | digital-only skips address/shipping, address validation, shipping selection, **client-sent prices ignored**, `expected_total` drift → 409, idempotency key replay, stock reservation created |
| Payment | confirm path verifies with provider (mocked), webhook captured → order paid, **duplicate webhook no-op**, invalid signature 401, amount mismatch rejected, late payment after reservation expiry, failure path releases stock, reconciliation job |
| Order | owner can view, other user 404, guest with token, cancel rules, status history written |
| Download | entitlement created on paid, link generation checks (owner, paid, limit, expiry, revoked), token single-use & expiry, counter atomic, log rows, revocation, refund revokes |
| Refund | full/partial, gateway call idempotent, restock option, entitlement revoked, audit log |
| Admin catalog | CRUD + permissions + audit, archive vs delete, publish validation per type |
| Search | prefix match (`iphon` → iPhone products), typo suggestion, filters, sorts, unknown filter → 422 |

### Security suite (`tests/Security`)
- **Route authorization sweep:** every `/api/v1/admin/*` route returns 401 unauthenticated, 403 for a customer, 403 for staff lacking the permission, 403 `two_factor_required` for staff without 2FA.
- **IDOR sweep:** for each owner-scoped endpoint, user B gets 404 on user A's resource.
- Mass assignment attempts (role, status, price fields) are ignored/rejected.
- CSRF required on cookie-authenticated mutations.
- Rate limits trigger 429 on auth, checkout, downloads.
- Upload validation: wrong MIME, polyglot file, oversized, SVG.
- Log redaction: password/token never appear in log output.
- Security headers present on API responses.

## 4. Frontend tests
- Components: ProductCard (sale badge, rating, out-of-stock), VariantPicker (unavailable combos disabled), CartDrawer, filters (URL sync), forms (Zod errors, server error mapping), money formatting.
- Hooks/services: api-client envelope unwrapping, error typing, CSRF bootstrap, request id header.
- Each page-level feature has loading, empty and error state tests.

## 5. End-to-end journeys (§89)
Run on mobile, tablet, desktop projects:
1. Visitor → home → category → filter/sort → product → variant → add to cart → drawer → checkout as **guest** (physical) → pay (Razorpay test mode or FakeGateway in CI) → confirmation → order page via email link.
2. Registered customer → buy **digital** product → downloads page → download succeeds → limit reached message after N downloads.
3. Guest cart → login → cart merged.
4. Search typo (`iphon`) → suggestions → result.
5. Admin (with 2FA) → create product via wizard (autosave) → publish → visible on storefront after revalidation.
6. Admin → mark order shipped with tracking → customer sees status.
7. Admin → refund digital order → customer download revoked.
8. Keyboard-only checkout pass (accessibility).

## 6. Seed data for manual/visual testing
`php artisan migrate:fresh --seed` creates the dataset in DATABASE.md §5 (10 categories, 50 products incl. 10 digital, 5 brands, 10 customers, 20 orders, 5 coupons, 5 reviews). E2E uses a smaller deterministic `E2ESeeder`.

## 7. Commands
```bash
# backend (from backend/)
composer test           # pest --parallel (MySQL ecom_testing_N)
composer lint           # pint --test && phpstan analyse (level 6)
# frontend (from frontend/)
npm run test            # vitest run
npm run lint            # eslint
npm run typecheck       # next typegen && tsc --noEmit
# e2e (from tests/e2e/, API on :8000 and web on :3000 running)
npm run e2e             # playwright test — global setup runs migrate:fresh --seed
```

## 8. Definition of done (per feature)
Tests written and green · lint/typecheck clean · authorization tests for new endpoints · mobile + desktop verified (Playwright screenshots or manual) · loading/empty/error states present · docs updated.
