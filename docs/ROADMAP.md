# Roadmap

> Phases follow PRD §95. Work never advances to the next phase automatically — each phase ends with a review.
> Note: the PRD's §99 prompt calls the catalog "Phase 2"; this roadmap keeps §95 numbering (Design System = Phase 2, Catalog = Phase 3). §100 lists Payments/Orders as separate steps; here they are part of Phase 5 (Checkout) because an order cannot exist without payment handling.

## Status

| Phase | Name | Status |
|---|---|---|
| 0 | Architecture | ✅ Done |
| 1 | Foundation | ✅ Built & tested locally — hosting verification (DEPLOYMENT **VERIFY** items) pending Hostinger/Vercel access |
| 2 | Design system | ✅ Palette, type and core components (owner palette, D15); component gallery pending |
| 3 | Catalog | ✅ Built (DB-backed catalog, variants, stock ledger, search, admin CRUD) |
| 4 | Customer experience | ✅ Built (home, shop, PDP, bag + drawer, wishlist, account, orders, downloads, reviews with photos) |
| 5 | Checkout, payments, orders | ✅ Built; Razorpay hardened + tested against a faked API (2026-10-26) — needs real keys to verify end to end |
| 6 | Digital products | ✅ Built (private files, single-use links, limits, expiry, revocation, logs) |
| 7 | Admin | ✅ Built (dashboard, products, categories/brands, inventory, orders, customers, digital, coupons, reports, content, settings, audit); staff management (Admin → Staff); CSV import/export, support tickets pending |
| 8 | Marketing | 🟡 Owner-selected set built 2026-10-12: free-delivery progress, gift wrapping + message, abandoned-bag reminder, Pairs well with, reviews with photos + moderation + review request email after delivery and "Write a review" on delivered orders, back-in-stock alerts, WhatsApp share/ask, honest low-stock count + reassurance, corporate gifting enquiries (Admin → Marketing / Reviews / Enquiries, Settings → Marketing). Promotions engine, subscribers, price-drop alerts pending |
| 9 | Security audit | — |
| 10 | Performance | — |
| 11 | Testing hardening | — |
| 12 | Production | — |

Universal exit gate for every phase (CLAUDE.md "Production Quality Gate"): tests pass · lint/typecheck clean · no known critical security issue · mobile + desktop verified · loading/empty/error states · authorization verified · transactions verified · logging where required · docs updated.

## Phase 0 — Architecture
Deliverables: `docs/{PRD,ARCHITECTURE,DATABASE,API,SECURITY,DEPLOYMENT,TESTING,ROADMAP}.md`, `CLAUDE.md`, git repo.
Exit: user review of decisions D1–D10 and risks R1–R12.

## Phase 1 — Foundation
- Monorepo scaffold: `frontend/` (Next.js 16, TS strict, Tailwind v4, shadcn/ui init, ESLint, Vitest), `backend/` (Laravel 13, Pest, Pint, Larastan), `tests/e2e` (Playwright), `deployment/`.
- Backend: API v1 routing, `ApiResponse` envelope, exception handler (§69/§86), `AssignRequestId`, log channels + redaction processor, security headers middleware, CORS, Sanctum SPA, database sessions, users/roles/permissions (spatie with PRD table names), `PermissionSeeder`, `DemoSeeder` (dev only), auth endpoints (register/login/logout/reset/verify/2FA), `/me`, `/admin/me`, `EnsureAdminTwoFactor`, `audit_logs` + `Audit` service, `settings`, `/health`, `/settings/public`, scheduler + database queue config, `Money` value object.
- Frontend: env validation, api-client, request id, base layouts (storefront header/footer/bottom-nav shell, account shell, admin shell), auth pages, token file scaffold, loading/error/empty/not-found primitives.
- CI workflows (backend matrix MySQL/MariaDB, frontend, e2e smoke).
- **Hosting verification:** fill every **VERIFY** in DEPLOYMENT.md; deploy a hello-world Laravel to `api-staging` + Next.js to staging and prove cookie login across subdomains.
Exit tests: auth feature suite, route-authorization sweep, envelope/request-id/redaction tests, Money unit tests, E2E login on mobile + desktop.

**Phase 1 result (2026-10-07):** backend 68 Pest tests (parallel, MySQL) · Pint + Larastan L6 clean · frontend 20 Vitest tests · ESLint + strict typecheck + production build clean · Playwright 16 journeys across mobile/tablet/desktop with axe (serious/critical = 0). CI workflows in `.github/workflows` (backend matrix MySQL 8 / MariaDB 10.11 — first run pending a GitHub remote).
Placeholder routes (`components/placeholder-page.tsx`) keep every navigation link working until its phase replaces them: `/shop`, `/categories`, `/search` (Phase 3), `/wishlist`, `/cart` (Phase 4), `/faq`, `/pages/{contact,shipping,returns,privacy,terms,refund-policy}` (Phase 7 CMS). E2E `navigation.spec.ts` fails if any chrome link 404s.
**Superseded 2026-10-07 — full flow built at owner request.** The preview catalog below was replaced by the real API; `demo-data.ts` was removed and demo products are now seeded by `CatalogSeeder`.

**Preview catalog (added 2026-10-07 at owner request):** luxury theme from the owner's palette (ARCHITECTURE D15) plus a demo catalog (D16) powering home, shop, categories, category, product, digital-product and search pages. Add-to-bag / wishlist show a "next update" notice until Phases 4–5. Phase 3 must replace `features/catalog/catalog.ts` internals with API calls and remove `demo-data.ts` + `public/demo/`.
Carried forward: password-change and active-sessions **UI** (API done) → Phase 4; hosting verification + cookie login across real subdomains → before Phase 5; `TrustProxies` configuration → Phase 12.

## Phase 2 — Design system
Tokens (type scale, color, spacing, radius, shadow, motion), brand identity direction (name/logo TBD by owner), components: Button, IconButton, Input, Select, Checkbox, Radio, Textarea, Form field, Card, Badge, Price, Rating, Modal/Dialog, Drawer/Sheet, Toast, Tabs, Accordion, Table, Pagination, Skeleton, EmptyState, ErrorState, Breadcrumb, Header, BottomNav, Footer; responsive grid; reduced-motion handling; component gallery route (dev only).
Exit: component tests, axe clean gallery, screenshots at 360/390/768/1280/1920.

## Phase 3 — Catalog
Backend: brands, categories, attributes/values, products (type registry: physical + digital), variants, product media + media assets (upload, WebP variants), inventory + ledger (admin adjustments), search index + `DatabaseSearchEngine`, filters, sorting, related products, public catalog endpoints, admin catalog CRUD endpoints + audit.
Frontend: `/shop`, `/category/[slug]`, `/product/[slug]`, `/digital/[slug]`, `/search`, ProductCard, filters (URL state), sort, gallery (hover zoom / swipe + pinch), variant picker, SEO metadata + JSON-LD, sitemap; admin product list/editor (basic form; full wizard in Phase 7), categories, brands, attributes, inventory screens.
Exit: catalog unit + API tests, search tests (`iphon`), admin permission tests, E2E browse journey.

## Phase 4 — Customer experience
Homepage via CMS sections (`home` page, seeded), cart (guest/user, merge, save for later) + cart drawer, wishlist, account dashboard (profile, addresses, sessions, 2FA), recently viewed (client), reviews read-only display, newsletter subscribe, analytics beacon.
Exit: cart/wishlist suites incl. merge, E2E add-to-cart on all viewports.

## Phase 5 — Checkout, payments, orders
Tax engine, shipping engine, coupons (apply in cart), checkout endpoints + UI (Contact → Address → Shipping → Payment), reservations, order creation + order numbers, `PaymentGateway` contract, `FakeGateway`, **Razorpay**, webhooks + idempotency + reconciliation, order pages (customer + guest token), order state machine, notifications (ORDER_CONFIRMATION, PAYMENT_SUCCESS), unpaid-order expiry, admin minimal order list for testing.
Exit: full pricing/tax/coupon/inventory unit suites, payment & webhook feature suites, concurrency tests, E2E guest checkout physical.

## Phase 6 — Digital products
Digital settings, file upload (chunked, private disk), entitlements on payment, downloads page, link + token flow, limits/expiry, logs, revocation, refund hook, DIGITAL_PRODUCT_READY email, optional license keys, LiteSpeed large-file strategy verified on staging.
Exit: download security suite, E2E digital purchase + download.

## Phase 7 — Admin
Dashboard KPIs (§41), product wizard with autosave (§43), CSV import/export (§44–§45), order management (status, shipments, tracking, cancel, refunds), customers (+ analytics §74), coupons UI, reviews moderation, support tickets (customer + admin), CMS (pages/sections, blog, banners, menus, FAQ), media library, reports (sales, products, customers, inventory, finance), staff & roles, settings (store, checkout, payments, tax, shipping), audit log viewer, remaining notifications (shipped/delivered/refund).
Exit: admin permission sweep, audit coverage tests, E2E admin journeys.

## Phase 8 — Marketing
Promotions engine (v1 types), coupon enhancements, subscribers & export, abandoned cart recording (+ reminders if in scope), wishlist price-drop/back-in-stock notifications.
Exit: promotion/stacking unit tests, notification job tests.

## Phase 9 — Security audit
Review against SECURITY.md + OWASP ASVS L2: authn/z, API, admin, payments, downloads, uploads, headers, dependency audit, secrets scan, rate limits. Fix findings, add regression tests.

## Phase 10 — Performance
`EXPLAIN` hot queries on seeded data (scale seed to ~5k products), N+1 detection (`Model::preventLazyLoading` in dev), caching review, image sizes, bundle analysis, ISR/revalidation tags, Lighthouse budgets.

## Phase 11 — Testing hardening
Fill coverage gaps, E2E across all viewports, accessibility audit (WCAG 2.2 AA manual pass with keyboard + screen reader), load smoke test against staging within host limits.

## Phase 12 — Production
DEPLOYMENT.md §9 checklist: domains, SSL, DB, backend, frontend (Vercel Pro), mail auth, live payments, cron, backups + restore drill, monitoring, legal review, launch.

## Release scope

**V1** (PRD §102): Customer — home, shop, search, categories, product, cart, wishlist, checkout, payment, orders, digital downloads, account, reviews, support. Admin — dashboard, products, categories, inventory, orders, customers, digital products, coupons, content, reports, settings. Gateways: Razorpay (+ Stripe if business eligible). Single currency (INR), English.

**V2** (§103): subscriptions, bundles, gift cards, advanced promotions (BxGy, bundles, flash sales), abandoned-cart automation, email campaigns, AI product descriptions, AI search, AI recommendations, advanced analytics, loyalty, PWA, carrier integrations (Shiprocket etc.), SMS/WhatsApp, dedicated search engine.

**V3** (§104): multi-vendor marketplace, seller dashboards/payouts/commissions, native mobile apps (token auth), advanced AI, multi-currency charging, Tamil/Hindi, advanced fulfillment.

**Explicitly out of V1** (§101): marketplace, drag-and-drop builder, native app, AI features, loyalty, crypto, live shopping, complex subscriptions, many gateways/carriers.


## Build log — 2026-10-07 (Phases 3–7 core)
- Backend: 109 Pest tests (catalog, cart, coupons, GST, shipping, checkout, idempotency, payment verification, duplicate webhooks, oversell protection, unpaid-order expiry, downloads security, refunds, admin permissions, every admin list endpoint) · Pint + Larastan L6 clean.
- Frontend: 34 Vitest tests · ESLint + strict typecheck + production build clean.
- E2E: 45 Playwright journeys across mobile/tablet/desktop with axe (guest checkout with coupon and a failed-then-successful test payment, digital purchase + download, admin product creation → storefront, admin ship/deliver).
- Still open: product reviews, support tickets, CSV import/export, promotions engine, abandoned carts, email templates beyond order confirmation, Razorpay live verification, hosting deployment.

## Build log — 2026-10-08 (fulfilment, couriers, wishlist)
- Order stages: admin order page shows a stage tracker (Paid → Processing → Packed → Shipped → Out for delivery → Delivered) with next-step buttons; the order list has a one-click "Next step" column. `OrderStateMachine::advance` records each intermediate stage; staff cannot skip past Shipped without tracking.
- Shiprocket (`app/Domain/Shipping`): marking an order Packed creates the Shiprocket order (Prepaid), assigns an AWB and books pickup; failures keep the order Packed and offer "Retry booking". Tracking webhooks (`/api/webhooks/delivery-updates`) advance orders automatically. Needs `SHIPROCKET_*` env (see DEPLOYMENT.md) — verify on staging with a real API user.
- Payments: online only (no COD). Razorpay is implemented; Settings shows connection/test-mode status and the webhook URL. Live verification still needs keys.
- Wishlist: hearts toggle (red when saved) on cards and the product page; header and bottom-nav show a red count badge.
- Backend 119 Pest tests · Larastan clean · E2E commerce spec incl. stage flow and wishlist.
- Excel exports (`app/Domain/Reports/Export`, PhpSpreadsheet): branded workbooks with frozen headers, filters, currency/date formats, totals and print setup for products, inventory, orders (+ items), customers, digital access (+ download log), coupons, and a full report (summary, daily sales, by product/category, then every permitted area). Shared `SalesReport` feeds both the Reports screen and the workbook.
- Fixed: dashboard crash when switching to 7 days (chart labels were looked up by position). Buy now is now a solid button.
- GST tax invoices (`app/Domain/Invoices`, dompdf): issued once per order when payment is captured, numbered INVyy-yy/NNNNNN consecutively per Indian financial year (locked sequence), seller details snapshotted; PDF attached to the order confirmation email and downloadable by the customer (or guest token) and by staff. Seller legal name, GSTIN (validated against the business state) and address are set in Settings. Credit notes for refunds are not yet issued.
- Staff management (`StaffController`, `Domain/Identity/{StaffAccess,Actions/InviteStaffMember,UpdateStaffMember}`): invite by email (set-password link), promote existing customers, change role, deactivate/reactivate (signs out everywhere), reset 2FA, resend invitation; password-confirmed and audited. E2E `staff.spec.ts` (phone + desktop, axe).
- Homepage hero slideshow (`HeroSlider`, `hero_slides`, Admin → Content → Homepage hero): crossfading full-bleed photos with slow zoom, per-slide copy and button, autoplay that pauses on hover/focus/hidden tab and has a pause button (WCAG 2.2.2), no autoplay for reduced motion, swipe + arrow keys. Built-in candle/Jesmonite slides (Pexels, credited) until the store uploads its own. E2E `hero.spec.ts`.
- Roles & access (`RoleController`, `ManageRole`, `PermissionCatalog::groups`): owners create custom roles and tick access per admin page, edit default roles, delete unused roles; one role can be given to any number of staff. E2E in `staff.spec.ts`.
- Chrixlin brand pass: name defaults, slogan “Indulgence, poured by hand.”, dessert-candle hero slides (AI-generated, credited in public/demo/CREDITS.md), Dessert Candles / Jesmonite navigation, studio editorial copy; the digital section hides itself when no digital products exist.
- Store pages (`StorePagesSeeder`, all environments, create-only): Terms, Privacy, Shipping, FAQ, Contact for Chrixlin. Made-to-order explanation (no returns/refunds) only in Terms and FAQ; returns/refund links and copy removed from footer, homepage, product page, checkout and invoice. Page bodies support `## ` headings.
- Admin → Content has tabs: Homepage hero (launch slides seeded into the DB by `HeroSlidesSeeder`, once), Site text (`config/site_content.php` whitelist, stored in settings `content.site_text`, storefront reads `/site-content` with default fallback) and Pages. `StorePagesSeeder` now updates pages the owner never edited (hash-tracked). Damaged-on-arrival (24 h, replacement) and grievance (acknowledge 48 h, resolve 1 month, E-Commerce Rules 2020) sections in Terms/FAQ. Hero autoplay no longer pauses on mouse hover.
- Per-product free delivery (`products.free_shipping`, product editor "Delivery" panel, "Free delivery on this piece" on product pages); no store-wide free-over threshold (standard `free_over` cleared). Customer passwords 6+ characters with no breach check (staff unchanged). Sign-in/sign-up keep `next` across the header icon, login↔register links and register redirect; checkout offers sign-in back to checkout. E2E `return-after-auth.spec.ts`.
- Speed & feedback (server is in Lithuania, ~200 ms from India): global top progress bar on every internal link click, admin menu highlights instantly with a per-link spinner (`useLinkStatus`), `loading.tsx` skeletons for admin and product/category/shop/search/content routes, `preconnect` to the API, optimised images cached 30 days. Stale `apps/nodetest` Node process removed from the server.
- Admin "Cancel & refund" on paid orders (full amount cancels via automatic Razorpay refund; lower amount = partial refund) and `OrderRefundedNotification` emailed to the customer after every refund. E2E `cancel-refund.spec.ts`.
- Shiprocket test mode (`SHIPROCKET_TEST_MODE`): Shiprocket has no sandbox, so packing an order only creates the free Shiprocket order (status `test_created`) — no AWB, wallet charge or pickup. Shown in Settings and on the order page.
- Returning customers: checkout shows last order's delivery address (`SavedAddress`, from `order_addresses`) for confirmation every time, with "Change address" opening the form pre-filled; phone pre-filled. Guests: nothing saved. E2E `saved-address.spec.ts`.

## Build log — 2026-10-26 (Razorpay go-live hardening)
- `PaymentGateway::fetchStatus` + `ReconcilePayments` (every 15 min): payments whose webhook and browser confirmation both went missing still end as paid orders.
- `orders:expire-unpaid` is now scheduled (every 5 min; it was never scheduled before, so unpaid orders held stock indefinitely) and reconciles each order with the provider before cancelling.
- Authorized-but-uncaptured Razorpay payments are captured server-side (amount/currency must match).
- Order creation, capture and refunds are no longer auto-retried; refunds send `X-Refund-Idempotency` so a repeated request cannot refund twice.
- Tests: `tests/Feature/Commerce/RazorpayTest.php` (10 cases: signature + server status, failed status, capture fallback, amount mismatch, webhook signature + dedupe, reconciliation, expiry safety, refund idempotency). Backend 240 tests green, Pint + Larastan clean.
- Still open: verify with real Razorpay test keys, then live keys; refund webhooks (`refund.processed/failed`) are not consumed yet — refunds are recorded on Razorpay's API acceptance.

## Build log — 2026-10-26 (SEO: Google can read the store)
- Sitemap (`/sitemap.xml`, 38 URLs on the demo catalog incl. product images), `/robots.txt`, JSON-LD Product + BreadcrumbList on product/digital pages, Organization + WebSite on home, Google Merchant feed `/feeds/google-merchant.xml` (16 physical products). These were planned in Phase 3 but had not been built.
- Tests: `features/catalog/structured-data.test.ts` (5) + `moneyToDecimal`. Frontend 48 tests, lint, typecheck, production build green.
- Owner actions: Search Console + sitemap submission, Merchant Center feed registration (DEPLOYMENT §7a).
- Next.js upgraded 16.4.0 → 16.4.3 (security advisory blocked builds). Released 2026-10-26 (`b564427`): sitemap submitted in Search Console (domain property `chrixlin.com`, DNS-verified); customer-number migration ran on production.
- Crawlability fix (2026-10-26): `/shop` and `/category/{slug}` now server-render their first page of products (`initialProducts` → TanStack `initialData`; `/shop` renders at request time), so product links are in the HTML Google reads (before: only the home page linked products — Search Console showed "Referring page: none"). Sitemap product entries carry `lastmod` from the API's new `updated_at`. Note: no API lets a shop force Google to index a page (Google's Indexing API is only for job postings/livestreams); new products reach Google via the auto-refreshing sitemap, internal links, and the Merchant feed.

## Build log — 2026-10-27 (Product videos)
- Admins can add up to 3 YouTube videos per product (Products → editor → Videos: paste a watch/youtu.be/shorts/embed/live link). Only the 11-char id is stored (`App\Support\Media\YouTube` validates host + id); add/remove are audited and invalidate the product's storefront cache.
- Storefront gallery shows videos after the images as thumbnails with a play badge; the privacy-enhanced `youtube-nocookie.com` player loads only when tapped (no third-party requests on page load). CSP + `next/image` allow `i.ytimg.com` and the nocookie frame. Also fixed: on desktop a tall info column stretched the 4:5 gallery wider than its column (overlapping the title); the gallery now aligns to the top of its row. Desktop product page is now gallery 5/11 · details 6/11 (was 1/2) so photos are slightly smaller. Videos play uncropped as a centred 16:9 frame inside the 4:5 gallery, over a blurred, darkened copy of the video thumbnail (brand black) instead of flat black bars.
- Product page above the fold (2026-10-27): on desktop (≥ ~1440×790) the whole photo/video and everything down to "Made for you" — price, colour + quantity, Add to bag / Buy now / wishlist, reassurance — is visible without scrolling. The gallery height follows the viewport (`--pdp-media-h`, `pdp-media` utility in `globals.css`) with thumbnails in a slim column on its left; share/ask-on-WhatsApp links moved beside the rating; the variant price line only shows when it differs from the headline price. Phones/tablets: square gallery and an always-visible Add to bag / Buy now / wishlist bar pinned above the bottom navigation (portalled to `<body>`, with a matching spacer after the footer); breadcrumb hidden below `md`. Below the fold, Description/Details, Customer reviews and Pairs well with share one rhythm (hairline + equal spacing, 4xl headings); a product with no reviews shows one quiet line instead of an empty histogram.
- Known: e2e `commerce.spec.ts` "admin manages the catalogue…" still fills a `Price` field the editor replaced with MRP + Discount earlier — test needs updating (not caused by this change).
- Tests: `tests/Feature/Admin/ProductVideoTest.php` (17), `product-gallery.test.tsx`. Backend 257 tests, frontend 52 tests, Pint + Larastan, lint, typecheck green.
- Deploy: run `php artisan migrate` (adds `product_media.type`, `video_id`; makes `path` nullable).
