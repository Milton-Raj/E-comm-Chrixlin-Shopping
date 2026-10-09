# Ecommerce Platform — Claude Code Development Rules

## Mission
Build a production-grade, scalable ecommerce platform supporting physical and digital products.
The application must be modern, secure, mobile-first, accessible, fast, maintainable and modular.
Do not create a prototype-quality application.

## Project map
- Requirements: `docs/PRD.md` (sections referenced as §N)
- Architecture & decisions (D1–D10), risks (R1–R12): `docs/ARCHITECTURE.md`
- Schema contract: `docs/DATABASE.md` · API contract: `docs/API.md` · Security & permission catalog: `docs/SECURITY.md`
- Deploy/env: `docs/DEPLOYMENT.md` · Tests: `docs/TESTING.md` · Phases & current status: `docs/ROADMAP.md`
- Code: `frontend/` (Next.js 16 on Vercel) · `backend/` (Laravel 13 API on Hostinger Premium) · `tests/e2e/` (Playwright) · `deployment/`

**Current phase is recorded in `docs/ROADMAP.md`. Only work inside the current phase. Never advance to the next phase without the user's explicit go-ahead.**

## Core principles
- Do not implement functionality without understanding the existing architecture.
- Never duplicate business logic.
- Never hard-code business rules, payment providers, tax rates, currencies or currency symbols.
- Never expose private digital-product files publicly.
- Never trust client-side payment confirmation or browser redirects as proof of payment.
- Never trust client-submitted prices or totals.
- Never expose database IDs; use UUIDs, slugs, order/ticket numbers.
- Never store secrets in source code.
- Never delete production financial records (orders, payments, refunds, ledgers, audit logs).
- Every sensitive admin action must be auditable.
- Every API endpoint must have explicit authorization.
- All user input must be validated; all queries must use parameter binding (no interpolated SQL, whitelisted sort/filter keys).
- All operations that can be repeated (webhooks, place order, confirm payment, refunds, cart merge) must be idempotent.
- Keep controllers thin. Business logic lives in `app/Domain/<Domain>/{Actions,Services}`. Actions own DB transactions.
- Write automated tests for all important business logic.
- Money: integer minor units + currency code, `App\Support\Money`; never floats.
- Authorization: check permissions (`can('orders.refund')`), never role names. The only role check is `Gate::before` for `super-admin`.
- Database: SQL must run on MySQL 8 **and** MariaDB 10.11.

## Frontend
Next.js (App Router) · TypeScript strict · Tailwind CSS v4 · shadcn/ui (Radix) · React Hook Form · Zod · TanStack Query.
- Mobile-first, responsive, accessible (WCAG 2.2 AA), fast, SEO-friendly, progressive enhancement where appropriate.
- Every data view has loading (skeleton), empty and error states.
- Use design tokens (`styles/tokens.css`) — no arbitrary Tailwind values (`[...]`) outside `components/ui`.
- All API calls go through `services/api-client.ts`; all UI strings through i18n `t()`.
- No excessive animations; respect `prefers-reduced-motion`. Touch targets ≥ 44px.
- Never put secrets in `NEXT_PUBLIC_*` variables.

## Backend
Laravel 13 · PHP 8.4 · MySQL/MariaDB · Sanctum SPA cookie auth · spatie/laravel-permission (tables renamed per DATABASE.md).
- REST API under `/api/v1`, response envelope per `docs/API.md` §1.2.
- FormRequests for validation; Policies / `can:` middleware for authorization; API Resources for output.
- Queued jobs (database queue) for email, images, search indexing, reports, webhooks processing; events/listeners for side effects; notifications for messages.
- Logging via domain channels with redaction; `X-Request-ID` on every request; audit logging for admin mutations.
- Database transactions with row locks for inventory, coupons, order numbers.
- Shared hosting constraints: no Redis, no daemons, no Node on the server; background work is cron-driven (`schedule:run` every minute).

## Payments
Providers implement `PaymentGateway` (ARCHITECTURE §6.11) and are resolved from config. Payment success is confirmed server-side (webhook or server-to-server status fetch). Webhooks are authenticated, idempotent (unique provider+event_id), logged, retryable. `FakeGateway` is for local/tests only.

## Digital products
Files on the private disk only. Downloads require: authenticated owner (or guest order token), paid order, valid entitlement, download-limit and expiry validation, single-use short-lived token. Record every download event. Support revocation.

## Security
CSRF · XSS (sanitize stored HTML, CSP) · SQL injection protection · rate limiting · secure cookies · CORS limited to the storefront origin · security headers · HTTPS · password hashing · admin 2FA on by default (the owner may switch the staff requirement off in Settings → Security; password-confirmed, audited, and owners/administrators are emailed) · audit logging. Never log passwords, tokens, secrets, card data, cookies or authorization headers.

## UI
Premium modern ecommerce. Prioritize visual hierarchy, whitespace, product imagery, clear CTAs, fast navigation, excellent mobile experience. Avoid generic templates, heavy gradients/shadows, clutter, tiny text, popups, unnecessary animation.

## Development process
Before implementing a major feature:
1. Inspect the repository and relevant docs.
2. Understand the existing architecture; identify affected modules.
3. Explain the implementation approach.
4. Implement the smallest coherent change.
5. Write/update tests. Run tests. Fix errors.
6. Review for security, responsive behavior and performance.
7. Update documentation (DATABASE/API/ARCHITECTURE when contracts change; ROADMAP status).

Never rewrite working modules unnecessarily. Do not add dependencies without a one-line justification. Use current stable versions.

## Commands
```bash
# services (MySQL 8 + Mailpit)
docker compose -f deployment/local/docker-compose.yml up -d
# backend (from backend/; PHP from ~/.config/herd-lite/bin)
php artisan serve --port=8000               # API at http://localhost:8000/api/v1
php artisan migrate:fresh --seed            # dev data incl. admin@example.test (dev only)
php artisan schedule:work                   # local scheduler + queue drain
composer test                               # pest --parallel
composer lint                               # pint --test && phpstan analyse
# frontend (from frontend/)
npm run dev | npm run test | npm run lint | npm run typecheck | npm run build
# e2e (from tests/e2e/; needs API :8000 + web :3000)
npm run e2e
```

Next.js 16 note: read `frontend/node_modules/next/dist/docs/` before using unfamiliar APIs (Cache Components, `proxy.ts`, `error.tsx` uses `retry`). Reading cookies/time/random values in Server Components needs `<Suspense>` or `'use cache'`.

## Git
Branches: `main` (production), `develop` (integration), `feature/*`, `bugfix/*`, `release/*`. Feature flow: requirement → implementation → tests → review → merge. Commit only when the user asks.

## Testing (required areas)
Authentication · authorization · product catalog · pricing · discounts · cart · inventory · checkout · payments · orders · refunds · digital downloads · admin permissions. See `docs/TESTING.md`.

## Production quality gate
Do not declare a feature complete until: tests pass · no known critical security issue · mobile layout verified · desktop layout verified · error/loading/empty states handled · authorization verified · database transactions verified · logging implemented where required · documentation updated.

## Critical rule
Never sacrifice architecture, security or data integrity merely to finish a feature faster.
