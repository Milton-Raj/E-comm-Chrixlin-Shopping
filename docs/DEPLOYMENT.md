# Deployment

> Topology: Next.js on **Vercel**, Laravel API + DB + files on **Hostinger Premium** (ARCHITECTURE D1).
> Items marked **VERIFY** must be checked in hPanel during Phase 1 and this document updated with the actual values.

## 1. Environments

| Env | Frontend | API | DB | Notes |
|---|---|---|---|---|
| local | `http://localhost:3000` | `http://localhost:8000` (`php artisan serve`) | Docker MySQL 8 `ecom` | Mail → Mailpit (UI `http://localhost:8025`); `FakeGateway` + Razorpay test keys |
| staging | `https://staging.<domain>` (Vercel, Hobby OK while non-commercial) | `https://api-staging.<domain>` (Hostinger, second website/subdomain) | separate DB | Razorpay **test** mode; basic-auth / `noindex` |
| production | `https://www.<domain>` (Vercel **Pro**) | `https://api.<domain>` | production DB | Live keys |

Same registrable domain for frontend and API in every non-local environment (cookie auth). Vercel preview deployments (`*.vercel.app`) only render public pages against staging.

## 2. Local development (macOS)

Prerequisites (one-time):
1. **PHP 8.4 + Composer** — installed with Laravel's php.new (`/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.4)"`) into `~/.config/herd-lite/bin` (added to `~/.zshrc`). Laravel Herd works too. Match Hostinger's PHP version (**VERIFY** in hPanel).
2. **Docker Desktop** — runs MySQL 8 (3306, databases `ecom` + `ecom_testing*`) and Mailpit (SMTP 1025, UI 8025): `docker compose -f deployment/local/docker-compose.yml up -d`. Add `--profile mariadb` for a MariaDB 10.11 instance on 3307 to reproduce the production engine.
3. Node.js 22 LTS + npm.

Run:
```bash
# backend (http://localhost:8000)
cd backend && composer setup          # install, .env, key, migrate + seed
php artisan serve --port=8000
php artisan schedule:work             # local scheduler (drains the queue every minute)
# DemoSeeder (dev only): admin@example.test and <role>@example.test; password = DEMO_PASSWORD in .env

# frontend (http://localhost:3000)
cd frontend && cp .env.example .env.local && npm install && npm run dev

# e2e (both servers running; resets the DB first)
cd tests/e2e && npm install && npx playwright install chromium && npm run e2e
```

## 3. Environment variables

### Backend (`backend/.env`) — names only, values never committed
| Variable | Notes |
|---|---|
| `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG=false` (prod), `APP_URL=https://api.<domain>` | |
| `FRONTEND_URL=https://www.<domain>` | CORS, email links (`CORS_ALLOWED_ORIGINS` optional comma list overrides CORS) |
| `STORE_NAME`, `STORE_CURRENCY=INR`, `STORE_LOCALE=en-IN` | defaults; admin settings override |
| `ADMIN_IDLE_MINUTES=30`, `LOGIN_LOCKOUT_ATTEMPTS=10` | SECURITY.md §3 |
| `DEMO_PASSWORD` | **local only** — fixed password for DemoSeeder accounts |
| `ADMIN_REQUIRE_2FA` | `true` (default). Local dev may set `false`; ignored in production |
| `PAYMENT_TEST_GATEWAY` | `true` locally/staging; the test gateway is never offered in production |
| `REVALIDATE_URL`, `REVALIDATE_SECRET` | storefront cache refresh after admin changes |
| `SANCTUM_STATEFUL_DOMAINS=www.<domain>` | |
| `SESSION_DRIVER=database`, `SESSION_DOMAIN=.<domain>`, `SESSION_SECURE_COOKIE=true` | |
| `DB_CONNECTION=mysql|mariadb`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | |
| `CACHE_STORE=database`, `QUEUE_CONNECTION=database` | |
| `FILESYSTEM_DISK=public`, `DIGITAL_DISK=private` (later `r2`) | |
| `STORAGE_KEY`, `STORAGE_SECRET`, `STORAGE_BUCKET`, `STORAGE_ENDPOINT` | only when object storage is enabled (§92 `STORAGE_KEY`) |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` | |
| `PAYMENT_DEFAULT_GATEWAY=razorpay` | |
| `RAZORPAY_KEY_ID`, `RAZORPAY_KEY_SECRET`, `RAZORPAY_WEBHOOK_SECRET` | PRD §92 `PAYMENT_SECRET` / `PAYMENT_WEBHOOK_SECRET`, namespaced per provider |
| `SHIPROCKET_EMAIL`, `SHIPROCKET_PASSWORD`, `SHIPROCKET_WEBHOOK_TOKEN`, `SHIPROCKET_PICKUP_LOCATION` (+ `SHIPROCKET_PACKAGE_*`, `SHIPROCKET_DEFAULT_WEIGHT_GRAMS`) | Courier booking on "Packed" and tracking webhooks. API user from Shiprocket → Settings → API; webhook URL `https://api.<domain>/api/webhooks/delivery-updates` with the token as `x-api-key` |
| `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET` | when Stripe is enabled |
| `REVALIDATE_URL=https://www.<domain>/api/revalidate`, `REVALIDATE_SECRET` | on-demand ISR |
| `LOG_CHANNEL=stack`, `LOG_LEVEL=info`, `LOG_DAILY_DAYS=30` | |
| `HEALTH_TOKEN` | detailed `/health` |

### Frontend (Vercel project env)
| Variable | Notes |
|---|---|
| `NEXT_PUBLIC_API_URL=https://api.<domain>/api/v1` | |
| `NEXT_PUBLIC_SITE_URL=https://www.<domain>` | canonical URLs |
| `NEXT_PUBLIC_STORE_NAME` | storefront name in header/metadata |
| `NEXT_PUBLIC_MEDIA_URL=https://api.<domain>` (or CDN) | image loader |
| `NEXT_PUBLIC_RAZORPAY_KEY_ID` | public key only |
| `REVALIDATE_SECRET` | server-only (must match backend) |
| `API_INTERNAL_URL` | optional server-side base URL |

`frontend/lib/env.ts` validates env with Zod at build/start; build fails on missing variables.

## 4. Hostinger Premium — backend setup (one-time)

1. **hPanel checks (VERIFY and record here):** PHP version (≥ 8.3, target 8.4) and extensions (`pdo_mysql`, `mbstring`, `openssl`, `gd` (+ WebP), `intl`, `bcmath`, `fileinfo`, `sodium`, `zip`, `curl`); DB engine + version; SSH access; cron minimum interval; Git deployment; disk/inode quota; max upload/post size; `max_execution_time`; CDN availability on Premium; email sending limits.
2. Create subdomain `api.<domain>`; issue SSL (Let's Encrypt via hPanel); force HTTPS.
3. Create MySQL database + user (least privilege on that DB only).
4. SSH layout:
   ```
   ~/apps/ecom/                    # git clone (backend only is used)
   ~/apps/ecom/backend/.env        # outside any web root
   ~/domains/api.<domain>/public_html  →  symlink to ~/apps/ecom/backend/public
   ```
   If symlinking the document root is not permitted (**VERIFY**), fall back to: subdomain root = `public_html/api`, containing Laravel's `public/` contents with `index.php` paths adjusted to `../../apps/ecom/backend` (script in `deployment/hostinger/`).
5. `deployment/hostinger/.htaccess` (LiteSpeed): Laravel front controller, deny access to dotfiles, force HTTPS, cache headers for `/storage/media/*`, no PHP execution under `/storage`.
6. `php artisan storage:link` (public media only). Private disk stays at `backend/storage/app/private`.
7. **Cron** (hPanel → Cron Jobs): `* * * * * cd ~/apps/ecom/backend && php artisan schedule:run >> /dev/null 2>&1` (use full PHP binary path shown by hPanel, e.g. `/opt/alt/php84/usr/bin/php` — **VERIFY**).
8. Payment provider dashboard: webhook URL `https://api.<domain>/api/webhooks/payment/razorpay`, events `payment.captured`, `payment.failed`, `order.paid`, `refund.processed`, `refund.failed`; copy secret to `.env`.
9. DNS: `www` → Vercel (CNAME), apex → Vercel (A/ALIAS, redirect to www), `api` → Hostinger. Optionally proxy `api` through Cloudflare (WAF + CDN) — then allow-list the webhook path and make sure the real client IP header is trusted (`TrustProxies`).
10. Email: SPF, DKIM, DMARC records for the sending domain.

## 5. Deploy procedure — backend

`deployment/hostinger/deploy.sh` (run over SSH; later from GitHub Actions via SSH key):
```bash
set -euo pipefail
cd ~/apps/ecom
git fetch --tags origin && git checkout --force "$RELEASE_TAG"
cd backend
composer install --no-dev --optimize-autoloader --no-interaction
php artisan down --retry=60 --render="errors::503"     # only if migrations are not backward compatible
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan event:cache && php artisan view:cache
php artisan queue:restart
php artisan up
curl -fsS https://api.<domain>/api/v1/health
```
- Deploy from `release/*` tags only; `main` = production.
- `composer install` runs on the server (Composer available via SSH — **VERIFY**); if the plan's CPU limits make this unreliable, build `vendor/` in CI and upload an artifact via rsync.
- **Rollback:** checkout previous tag, `composer install`, re-cache; migrations must be backward compatible for one release (expand → migrate → contract) so code rollback doesn't need DB rollback.

## 6. Deploy procedure — frontend (Vercel)
- Vercel project root directory `frontend/`, framework Next.js, Node 22.
- `main` → production, `develop` → staging domain, PRs → preview.
- Build command `npm run build` (runs typecheck + lint in CI before Vercel builds).
- After backend deploy that changes API shapes, deploy frontend second; additive API changes keep both orders safe.

## 7. CI (GitHub Actions)
| Workflow | Steps |
|---|---|
| `backend.yml` | matrix {mysql:8.0, mariadb:10.11}: composer install → pint --test → larastan (level 6+) → pest (parallel) → composer audit |
| `frontend.yml` | npm ci → eslint → tsc --noEmit → vitest → next build → npm audit (high+) |
| `e2e.yml` (on PR to develop/main) | start MySQL + Laravel (`php artisan serve`) + `next start` → Playwright (mobile/tablet/desktop projects) + axe |
| `lighthouse.yml` | Lighthouse CI on key pages against preview/staging with §51 budgets |

## 8. Backups & monitoring
- Hostinger automatic backups (**VERIFY** frequency on Premium) + nightly `mysqldump --single-transaction` (scheduler) encrypted and copied off-site (e.g. R2/S3/Google Drive), retained 30 days. Private digital files backed up weekly off-site.
- **Restore drill** before launch and quarterly: restore to staging, run smoke tests.
- Monitoring: uptime checks on `/api/v1/health` and `/` (e.g. UptimeRobot/Better Stack); error alerts from `errors` channel (Slack/email via log handler or Sentry if added); queue lag reported by `/health` and alerted when > 5 min; failed webhook count alert.

## 9. Production checklist (Phase 12)
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, unique `APP_KEY`
- [ ] No demo users (`admin@example.test` absent); `DemoSeeder` never run; first super-admin created via `php artisan app:create-admin` with 2FA enforced at first login
- [ ] Live payment keys + webhook secret; test transaction + refund executed end-to-end
- [ ] HTTPS everywhere, HSTS, security headers verified (securityheaders.com)
- [ ] CORS/Sanctum domains correct; login works on production domains
- [ ] Cron running; queue lag < 1 min; scheduler heartbeat visible in `/health`
- [ ] Mail SPF/DKIM/DMARC pass; order email received
- [ ] Backups configured + restore drill done
- [ ] Legal pages reviewed by a professional; tax rates confirmed by accountant
- [ ] Lighthouse budgets met on home, category, product, cart
- [ ] Vercel Pro plan active
- [ ] Monitoring and alerts firing to a real inbox

## 10. Secret rotation
- `APP_KEY`: set new key, move old to `APP_PREVIOUS_KEYS`, deploy, re-encrypt encrypted columns via command, then remove old key.
- Gateway/webhook secrets: generate new in provider dashboard → update `.env` → `config:cache` → verify webhook → revoke old.
- `REVALIDATE_SECRET`: update Vercel + backend together (backend accepts old+new during the switch window).
