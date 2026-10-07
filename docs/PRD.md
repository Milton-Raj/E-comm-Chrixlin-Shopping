# Master Product Requirements Document — Next-Generation Multi-Product Ecommerce Platform

> Source of truth for product requirements. Section numbers (§N) are referenced throughout `/docs`.
> Reformatted to Markdown from the original document (v1.0); content unchanged except list compaction.
> Where architecture docs deviate from this PRD, the deviation and its reason are recorded in `ARCHITECTURE.md` → "Decisions & deviations".

| Field | Value |
|---|---|
| Document version | 1.0 |
| Product type | Full-stack ecommerce platform |
| Primary products | Physical + digital products |
| Target | Web + mobile responsive |
| Hosting | Hostinger Premium (see ARCHITECTURE.md: frontend moved to Vercel) |
| Development | Claude Code |
| Architecture | Custom modular ecommerce platform |
| Initial market | Global-ready, India-compatible |
| Design goal | Premium, modern, fast, conversion-focused |

## 1. Product vision
Customers can purchase:
- **Physical products:** electronics, clothing, accessories, books, home, beauty, food/non-perishable, custom products, any other physical merchandise.
- **Digital products:** PDFs, eBooks, courses, templates, software, design assets, audio, video, ZIP files, documents, licenses, downloadable resources, digital subscriptions (future).
- **Future product types** the architecture must allow: services, subscriptions, memberships, bundles, product variations, gift cards, coupons, pre-orders, limited inventory, license-based products, bookings, SaaS products.

Do not hard-code the application around only physical products. The database and product engine must be designed around a generic Product model with product-type-specific attributes.

## 2. Core product principle
Behave like a combination of Amazon-style discovery, Apple-style visual design, Shopify-style administration, Stripe-style checkout simplicity, Netflix-style personalized recommendations — without copying their UI. The product has its own brand identity.

## 3. Technology stack
- **Frontend:** Next.js, TypeScript, Tailwind CSS, shadcn/ui, Radix UI, React, React Hook Form, Zod.
- **Backend:** Laravel + MySQL, exposed as a REST API (`Next.js → REST API → Laravel → MySQL`).
- Alternative considered: Laravel Blade/Livewire/Alpine/Tailwind. **Recommended: Next.js + Laravel API + MySQL** (modern UI; frontend can move to CDN/cloud while backend stays).

## 4. Why not pure WordPress?
WooCommerce is fast to launch, mature, has a huge plugin ecosystem. But the project needs a fully custom UX and admin, a physical + digital product engine, future SaaS features, custom automation/AI/workflows/dashboards, advanced accounts, recommendation engine, future mobile app, API-first architecture — so WooCommerce is not the core.

## 5. Hosting architecture
`Internet → CDN/WAF → Next.js frontend → HTTPS/API → Laravel backend → MySQL DB + File storage`.

## 6. Application structure
```
commerce-platform/
├── frontend/   (app/ components/ features/ hooks/ lib/ services/ types/ utils/ public/)
├── backend/    (app/{Models,Services,Actions,Policies,Jobs,Events,Listeners,Notifications,Http}, database/, routes/, config/, tests/, storage/)
├── admin/
├── database/
├── docs/
├── tests/
└── deployment/
```

## 7. User types
Role-based access.
- **Customer:** register, login, browse, search, add products, checkout, pay, track orders, download digital products, review products, manage addresses/profile/wishlist.
- **Administrator:** manage everything — users, products, categories, orders, inventory, payments, discounts, shipping, digital products, website content, banners, analytics, staff.
- **Staff** (granular permissions): Product Manager, Order Manager, Customer Support, Inventory Manager, Marketing Manager, Content Manager, Finance Manager.

## 8. Admin permission system
Never `if user.role == admin` everywhere. Implement Role, Permission, RolePermission, UserRole.
Example permissions: `products.view|create|edit|delete`, `orders.view|edit|cancel|refund`, `customers.view|edit`, `inventory.view|edit`, `reports.view`, `settings.manage`.

## 9. Customer website navigation
- **Desktop:** Logo · Shop · Categories · New Arrivals · Best Sellers · Digital · Physical · Search · Wishlist · Account · Cart.
- **Mobile:** Logo + Search header; Home · Shop · Categories · Wishlist · Cart · Account.

## 10. Homepage
Must not look like a generic template.
1. Hero — large visual ("Discover Products Worth Buying. Everything you need. One beautiful marketplace." [Shop Now] [Explore Digital]), large product imagery.
2. Smart categories — visual cards (Electronics, Fashion, Digital, Home, Beauty, Books, Software, More).
3. Trending — horizontal carousel.
4. Best sellers — grid.
5. Digital collection.
6. New arrivals.
7. Personalized "Recommended for You" (logged in).
8. Promotional banner.
9. Why shop with us — Secure Payments, Fast Delivery, Instant Downloads, Easy Returns, Trusted Support.
10. Newsletter.
- Footer: Shop, About, Contact, FAQ, Shipping, Returns, Privacy, Terms, Refund Policy, Help Center, Social.

## 11. Product catalog
Product has: basic information, media, pricing, inventory, variants, categories, attributes, SEO, shipping, digital files, related products, metadata.

## 12. Product types
Field `product_type`: `physical`, `digital`, `service`, `subscription`, `bundle`. Implement physical + digital initially; architect for the rest.

## 13. Products table
id, uuid, sku, name, slug, short_description, description, product_type, status, brand_id, category_id, base_price, sale_price, cost_price, currency, tax_class, weight, length, width, height, stock_quantity, low_stock_threshold, is_featured, is_best_seller, is_new, is_active, seo_title, seo_description, created_at, updated_at.

## 14. Product images
Main image, gallery, thumbnail, zoom, video, 360° later. Table `product_media`: id, product_id, type, url, alt_text, sort_order, is_primary.

## 15. Product variations
E.g. T-shirt sizes S/M/L/XL × colors Black/White/Blue. Each variant: SKU, price, sale price, stock, weight, image. Tables: product_variants, variant_attributes, attributes, attribute_values.

## 16. Digital product engine
Never expose real file URLs publicly (bad: `/domain.com/files/product.pdf`). Flow: customer → authenticated request → authorization → temporary download URL → download. Formats: PDF, ZIP, MP4, MP3, DOCX, XLSX, PPTX, EPUB, images, software packages.

## 17. Digital download security
Signed URLs, expiration, download limits, user authorization, order verification, access logs, IP logging, device/session tracking, revocation. E.g. `download_limit = 5`, `expires_at = 2026-12-31`.

## 18. Customer dashboard
"Welcome, {name}" — Orders, Downloads, Wishlist, Addresses, Profile, Payments, Reviews, Notifications, Support.

## 19. Order management
- Physical states: Pending, Payment Processing, Paid, Processing, Packed, Shipped, Out for Delivery, Delivered, Cancelled, Refund Requested, Refunded, Failed.
- Digital states: Pending, Paid, Available, Downloaded, Refunded, Revoked.

## 20. Order number
Do not expose DB IDs. Use `ORD-2026-000001` or `ORD-8F3K72`. UUID used internally.

## 21. Cart
Guest cart, logged-in cart, persistent cart, quantities, variant selection, remove, save for later, coupon, shipping estimation, tax calculation. On login: guest cart + customer cart = merged cart.

## 22. Wishlist
Add, remove, move to cart, price-drop notification, back-in-stock notification.

## 23. Search
Not `LIKE '%keyword%'` long term. Search name, SKU, category, brand, description, attributes, tags. Typo tolerance, autocomplete, recent searches, popular searches, filters, sorting. E.g. "iphon" → iPhone, iPhone Case, iPhone Charger.

## 24. Filtering
Category, brand, price, rating, availability, product type, color, size, material, digital/physical, discount. Update without full page reload.

## 25. Sorting
Relevance, newest, price low→high, price high→low, popularity, rating, best selling, discount.

## 26. Checkout
Cart → Address → Shipping → Payment → Confirmation. No 7–8 step checkout.

## 27. Guest checkout
Mandatory. Offer account creation after payment.

## 28. Payment architecture
`PaymentGatewayInterface` with `RazorpayGateway`, `StripeGateway`, `PayPalGateway`, … India: Razorpay (UPI, cards, net banking). International: Stripe, PayPal. Providers configurable (eligibility depends on entity/account).

## 29. Payment states
initiated, pending, authorized, captured, failed, cancelled, refunded, partially_refunded. Never mark paid from a browser redirect; confirmation must come from provider/webhook.

## 30. Webhooks
`POST /api/webhooks/payment/{provider}` — authenticated, idempotent, logged, retryable. Duplicate event ID must not create duplicate orders/payments.

## 31. Tax engine
No hard-coded percentages. TaxClass, TaxRule, TaxRate. Future: GST, VAT, sales tax, regional.

## 32. Shipping engine
ShippingZone, ShippingMethod, ShippingRate, Carrier, Tracking. E.g. India {Standard, Express}, International {Standard, Express}.

## 33. Inventory
Inventory ledger (`InventoryTransaction`): +100 received, −2 order, +1 cancellation, −1 damage. Available = opening + purchases + returns − sales − damaged.

## 34. Stock reservation
Available / Reserved / Sold. Stock 10, A reserves 1 → available 9, reserved 1. Reservation expires if payment not completed.

## 35. Coupons
Percentage, fixed, free shipping, product-specific, category-specific, first order, minimum order, maximum discount, expiration, usage limit, per-customer limit. E.g. WELCOME10.

## 36. Promotions
Engine separate from coupons: B1G1, B2G1, 10% off category, ₹500 off above ₹5000, free shipping, flash sale, bundle discount.

## 37. Reviews
Rate, review, upload images/video. "Verified Purchase" badge only for verified purchasers.

## 38. Customer support
Support ticket: ticket_number, customer_id, order_id, subject, category, priority, status, assigned_to. Statuses: Open, Pending, In Progress, Resolved, Closed.

## 39. Notification engine
Channels: email, in-app, SMS, push; future WhatsApp. Events: order created, payment successful, shipped, delivered, refund, digital download, password reset, wishlist price drop, back in stock.

## 40. Email templates
ORDER_CONFIRMATION, PAYMENT_SUCCESS, ORDER_SHIPPED, ORDER_DELIVERED, DIGITAL_PRODUCT_READY, PASSWORD_RESET, WELCOME, REFUND_COMPLETED.

## 41. Admin dashboard
Revenue, orders, customers, products, inventory, conversion rate, AOV, refunds, digital downloads (e.g. Today's sales ₹48,250; Orders 127; Customers 89; Conversion 3.8%; AOV ₹1,420).

## 42. Admin sidebar
Dashboard · Catalog (Products, Categories, Brands, Attributes, Reviews) · Orders (All, Pending, Processing, Shipped, Delivered, Returns) · Customers · Inventory · Digital Products (Files, Downloads, Licenses) · Marketing (Coupons, Promotions, Banners, Campaigns, Email) · Reports (Sales, Products, Customers, Inventory, Finance) · Content (Pages, Blog, FAQ, Menus) · Settings.

## 43. Admin product creation
Wizard: Basic info → Product type → Pricing → Media → Inventory → Variants → Shipping → Digital files → SEO → Related products → Publish. Autosave drafts.

## 44. Bulk import
CSV (SKU, Name, Description, Price, Category, Stock, Brand, Product Type). Upload → Validate → Show errors → Preview → Import.

## 45. Bulk export
Products, orders, customers, inventory, coupons — CSV/Excel.

## 46. Media library
Images, videos, documents, digital products. Search, filter, upload, delete, replace, rename, metadata, alt text.

## 47. CMS
Homepage, About, Contact, FAQ, Privacy, Terms, Refund, Shipping, Blog, landing pages — no developer needed for basic content changes.

## 48. Page builder
No big drag-and-drop builder initially. Configurable sections: Hero, Product Grid, Product Carousel, Category Grid, Banner, Text, Image, Video, Testimonials, FAQ, Newsletter. Admin can add, move, hide, edit sections.

## 49. SEO
Per product: SEO title, SEO description, slug, canonical URL, OpenGraph image, schema data. Generate Product, Breadcrumb, Organization, FAQ, Article schema.

## 50. URL structure
`/`, `/shop`, `/category/{slug}`, `/product/{slug}`, `/digital/{slug}`, `/cart`, `/checkout`, `/account`, `/orders`, `/downloads`. Avoid `/product?id=1837`.

## 51. Performance
LCP < 2.5s, CLS < 0.1, INP < 200ms. Image optimization, WebP/AVIF, lazy loading, CDN, browser + server caching, DB indexes, pagination, code splitting, API caching.

## 52. Mobile-first
Mandatory. Design mobile → tablet → desktop → large desktop. Breakpoints: 360, 390, 430, 768, 1024, 1280, 1440, 1920 px.

## 53. Mobile UX
Bottom nav: Home, Shop, Search, Wishlist, Cart. Touch-friendly cards. Minimum touch target 44×44 px.

## 54. Design system
Centralized: typography, spacing, colors, radius, shadows, buttons, cards, inputs, modals, toast, tables, badges. No scattered arbitrary CSS values.

## 55. Visual style
Minimal, premium, clean, modern, high contrast, large imagery, soft shadows, subtle animation. Avoid overloaded gradients, excessive animation, tiny text, too many colors, 1990s layouts, huge popups, aggressive ads.

## 56. Micro-animations
Add to cart, wishlist, hover, image transitions, modal open, page transitions, loading, success. Respect `prefers-reduced-motion`.

## 57. Product card
Image with wishlist heart and SALE badge; brand; name; rating (count); price + compare-at price; [Add to Cart]. Simplified on mobile.

## 58. Product detail page
Breadcrumb; gallery + info (brand, name, rating, price, discount, variant selection, quantity, [Buy Now], [Add to Cart], delivery info, returns, warranty); description; specifications; reviews; related products; recently viewed.

## 59. Image zoom
Desktop hover zoom. Mobile swipe gallery + pinch zoom.

## 60. Cart drawer
After add to cart: "Product added ✓" drawer with items, subtotal, [View Cart], [Checkout].

## 61. Checkout UX
Contact → Address → Shipping → Payment. Visually clean; do not force registration/email verification/confirm steps.

## 62. Customer account security
Password hashing, email verification, password reset, session management, logout all devices, optional 2FA, rate limiting, login attempt protection. Never plaintext passwords.

## 63. Security (mandatory)
HTTPS, CSRF, XSS, SQL injection protection, rate limiting, input validation, output escaping, secure cookies, CORS, CSP, security headers, password hashing, authorization policies, audit logs.

## 64. Admin security
2FA; admin login rate limiting, session timeout, IP/device logging, audit logs.

## 65. Audit log
Every sensitive admin action: admin, action, before, after, timestamp, IP.

## 66. Core tables
users, roles, permissions, role_permissions, user_roles; products, product_variants, product_attributes, attributes, attribute_values, product_media; categories, category_products, brands; inventory, inventory_transactions; carts, cart_items; orders, order_items, order_addresses, order_status_history; payments, payment_transactions; shipping_zones, shipping_methods, shipping_rates, shipments, tracking_events; coupons, coupon_usages, promotions; reviews, review_media; wishlists, wishlist_items; digital_products, digital_files, digital_downloads; notifications; support_tickets, support_messages; pages, blog_posts, banners; tax_classes, tax_rates; settings, audit_logs.

## 67. Database rule
Important tables have id, uuid, created_at, updated_at. Soft deletes (`deleted_at`) where appropriate — not blindly for financial records. Orders/payments are immutable history.

## 68. API architecture
Versioned `/api/v1/`. E.g. `GET /products`, `GET /products/{slug}`, `POST /cart`, `POST /checkout`, `POST /orders`, `GET /orders/{id}`, `GET /account/downloads`; admin `/api/v1/admin/{products,orders,customers}`.

## 69. API response format
Success: `{"success": true, "data": {}, "message": null, "meta": {}}`. Error: `{"success": false, "message": "Validation failed", "errors": {}}`.

## 70. Background jobs
Queues for emails, digital processing, image processing, order notifications, payment reconciliation, inventory sync, reports, cleanup.

## 71. Cron jobs
Expire cart reservations, expire download links, abandoned carts, notifications, reports, clean temp files, retry failed jobs. (Verify Premium cron availability.)

## 72. Abandoned cart
Record customer, cart, products, value, created time, last activity. Future: 2h reminder → 24h optional coupon.

## 73. Analytics
Revenue, orders, AOV, conversion, visitors, top products/categories, returning customers, cart abandonment, refund rate, digital downloads. Ranges: today, 7d, 30d, 90d, 12m, custom.

## 74. Customer analytics
Total orders, total spent, AOV, last purchase, wishlist, downloads, returns.

## 75. Product analytics
Views, add-to-cart, purchases, conversion, revenue, returns, reviews, wishlist additions.

## 76–79. AI features (Phase 2 / V2)
- AI product description generation (short/long description, SEO title/description, features, tags).
- AI search: natural language → structured search.
- AI recommendations from purchase/browsing history, wishlist, category, price range, similarity.
- AI support chatbot (order status, product info, shipping, returns, downloads, FAQ) — must not hallucinate order status; account questions go through authenticated APIs with real data.

## 80. Email marketing
Subscribers: email, status, source, consent, created_at. Later campaigns: new product, sale, abandoned cart, back in stock, newsletter.

## 81. Legal
Privacy, Terms, Refund, Shipping, Digital Product Terms, Cookie Policy, Disclaimer. "Digital products cannot be returned after download" only if it matches actual policy and law.

## 82. Accessibility
WCAG 2.2 AA: keyboard nav, screen readers, labels, semantic HTML, contrast, focus states, alt text, accessible forms, reduced motion.

## 83. Internationalization
Architect for English, Tamil, Hindi. Currencies INR, USD, EUR, GBP, AED. Never hard-code ₹.

## 84. Currency engine
Never floats. Integer minor units (1999 = ₹19.99).

## 85. Logging
Auth, payments, orders, errors, API, admin, webhooks, downloads. Never log passwords, payment secrets, API keys, full card details.

## 86. Error handling
User: "Something went wrong. Please try again." Developer logs: exception, stack trace, request ID, user ID, endpoint, timestamp.

## 87. Request ID
Every API request has `X-Request-ID`.

## 88. Testing
Unit (pricing, discounts, tax, inventory, coupons, order calculations); feature (registration, login, cart, checkout, payment, order, download, refund); API tests for all major endpoints; security tests (authorization bypass).

## 89. E2E testing
Browser automation: visitor → product → cart → checkout → payment → order → download, on mobile, tablet, desktop.

## 90. Seed data
10 categories, 50 products, 10 digital products, 5 brands, 10 customers, 20 orders, 5 coupons, 5 reviews.

## 91. Demo admin
Development only: `admin@example.test`. Never deploy default credentials.

## 92. Environment variables
APP_ENV, APP_KEY, APP_URL, DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD, PAYMENT_SECRET, PAYMENT_WEBHOOK_SECRET, MAIL_HOST, MAIL_USERNAME, MAIL_PASSWORD, STORAGE_KEY, NEXT_PUBLIC_API_URL. Never hard-code secrets.

## 93. Git workflow
Branches: main, develop, feature/*, bugfix/*, release/*. Feature flow: requirement → implementation → tests → review → merge.

## 94–95. Claude Code phases
Never "build the whole site". Phases:
0. Architecture (architecture, ERD, API spec, folder structure, design system, security model — no UI).
1. Foundation (repo, frontend, backend, DB, auth, env config, API structure, error handling, logging).
2. Design system (typography, colors, buttons, cards, inputs, modals, tables, navigation, responsive).
3. Catalog (categories, products, variants, brands, media, search, filters).
4. Customer experience (homepage, shop, product page, cart, wishlist, account).
5. Checkout (address, shipping, tax, payment, order creation, confirmation).
6. Digital products (file management, secure download, limits, expiration, history).
7. Admin (dashboard, products, orders, customers, inventory, coupons, content, reports).
8. Marketing (promotions, coupons, email, abandoned carts, wishlist notifications).
9. Security audit. 10. Performance. 11. Testing (unit, integration, API, E2E, security, mobile, a11y, perf). 12. Production (DB, backend, frontend, SSL, domain, email, payment, cron, backups, monitoring).

## 96. CLAUDE.md rules
See `/CLAUDE.md` (adopted in full).

## 97–99. Phase prompts
- Phase 0: create `/docs/{ARCHITECTURE,DATABASE,API,SECURITY,DEPLOYMENT,TESTING,ROADMAP}.md` + `CLAUDE.md`; identify risks; do not implement features.
- Phase 1: production foundation only (repo, Next.js, Laravel, MySQL, env, API versioning, auth, users/roles/permissions, policies, response format, error handling, logging, request ID, migrations, base layout, design tokens, responsive layout, loading/error/empty states, test infra). Strict TS. Run tests, lint, typecheck. Don't auto-advance.
- Phase "Catalog": categories, brands, products, variants, attributes, media, pricing, inventory, status, search, filter, sort, detail API, related products; admin CRUD; frontend shop/category/listing/detail/cards/filters/sort/search. Tests must pass before checkout.

## 100. Roadmap order
Architecture → Foundation → Design system → Catalog → Cart/Wishlist → Checkout → Payments → Orders → Digital products → Admin → Marketing → Analytics → Security → Performance → Production.

## 101. Not in V1
Multi-seller marketplace, drag-and-drop builder, native app, AI recommendations, AI assistant, loyalty, crypto, multi-vendor commissions, live shopping, complex subscriptions, many gateways/carriers.

## 102. V1 scope
Customer: home, shop, search, categories, product, cart, wishlist, checkout, payment, orders, digital downloads, account, reviews, support.
Admin: dashboard, products, categories, inventory, orders, customers, digital products, coupons, content, reports, settings.

## 103. V2
Subscriptions, bundles, gift cards, advanced promotions, abandoned cart, email marketing, AI search, AI recommendations, advanced analytics, loyalty, PWA.

## 104. V3
Multi-vendor marketplace, seller dashboard/payouts, commission engine, mobile apps, advanced AI, international expansion, multi-currency, multi-language, advanced fulfillment.

## 105. Hostinger decision
Premium is good for development, MVP, initial launch, small/medium catalog, low/moderate traffic. Not for huge marketplace, millions of products, massive concurrency, huge video libraries, large-scale digital distribution, heavy AI. Build so infrastructure can be upgraded later.

## 106. Final architecture
Frontend Next.js/TS/Tailwind/shadcn · Backend Laravel REST + queue + events + notifications · MySQL · app cache + CDN/browser caching · private digital storage + public media · payment abstraction (Razorpay, Stripe, future) · secure session/token auth, RBAC, admin 2FA · DB search first, dedicated engine later · Hostinger Premium initially · GitHub + Claude Code + automated tests · Future: AI, mobile, PWA, subscriptions, marketplace.

> "Build a real ecommerce platform, not a collection of pages that happens to have a shopping cart."
