# Rekasurya Store

**Pusat Produk Energi Terbarukan dan Kebutuhan Proyek** — a single-vendor B2B/B2C
e-commerce platform for **PT Rekasurya Primadaya** selling solar panels, inverters,
lithium batteries, PLTS packages, project-surplus goods, and installation services.

Built with **Laravel 13 · PHP 8.4 · MySQL 8 · Blade · Tailwind CSS v4 · Alpine.js · Vite**.

The catalog, search, filtering, cart, checkout, and quotation flows are modelled on
large Indonesian marketplaces while remaining a single-vendor store. It is **not** a
copy of any existing site — design, code, and content are original.

---

## Table of contents

1. [Features](#features)
2. [Tech stack](#tech-stack)
3. [Requirements](#requirements)
4. [Local installation](#local-installation)
5. [Demo accounts](#demo-accounts)
6. [Production installation (VPS)](#production-installation-vps)
7. [Shared hosting installation](#shared-hosting-installation)
8. [Database configuration](#database-configuration)
9. [Storage link & uploads](#storage-link--uploads)
10. [Cron & queue](#cron--queue)
11. [Building frontend assets](#building-frontend-assets)
12. [Creating an admin user](#creating-an-admin-user)
13. [Database backup](#database-backup)
14. [Testing](#testing)
15. [Security](#security)
16. [Project structure](#project-structure)
17. [Further documentation](#further-documentation)

---

## Features

**Storefront**
- Homepage with admin-managed hero banners, category shortcuts, curated rows
  (featured, PLTS packages, newest, promo, surplus, by-brand, most-viewed, top-rated),
  articles, testimonials, and newsletter.
- Unlimited nested categories, brands, and a **dynamic per-category attribute** system.
- Fast search (MySQL FULLTEXT with a portable LIKE fallback) + debounced autocomplete.
- Dynamic, shareable filters in the query string (category, brand, price, condition,
  stock, rating, promo/new/clearance/ready/quotation) and 8 sort modes.
- Product detail: gallery, variants, specs table, bundle contents, documents, reviews
  with rating distribution, verified-purchase badges, Q&A, related/similar/bought-together/
  recently-viewed, WhatsApp consultation, and a mobile sticky purchase bar.
- Every product has a **unique SEO-friendly URL**; old slugs 301-redirect automatically.
- Wishlist (guest + user, mergeable, shareable), comparison (≤4, same category).
- Project-surplus / clearance / open-box / used items with full condition disclosure and
  mandatory condition acknowledgement before checkout.

**Cart → checkout → order**
- Guest & user carts that merge on login; save-for-later; server-validated stock & coupons.
- Server-authoritative pricing — **all totals are recomputed on the server**; the client
  cannot alter prices, discounts, shipping, or tax.
- Volumetric shipping: `billable = max(actual, volumetric)`, divisor configurable per
  courier/service (never hardcoded), plus packing/handling/insurance/free-shipping.
- Idempotent checkout (double-click safe), DB transaction, and temporary **stock
  reservations** that auto-expire.
- Order status lifecycle with full history, secure token-based tracking, printable +
  PDF invoices with non-guessable URLs.
- Adapter-based payments (manual transfer + reference VA gateway) with **signature-verified,
  replay-safe, idempotent webhooks**.

**Quotation (RFQ)**
- Project customers submit RFQs with BOQ uploads; sales price them (revision history) and
  convert approved quotes into payable orders.

**Admin panel**
- RBAC with 7 roles and granular permissions.
- Dashboard KPIs + sales chart; CRUD for products/variants/attributes, categories, brands,
  stock (ledger), warehouses, coupons, banners, pages, articles, FAQs; order processing,
  shipping-cost confirmation, payment verification; review moderation; quotation pricing;
  customers; settings; users/roles; and an audit log.

**Foundations**
- Immutable **stock ledger** (movements) — stock can never go negative.
- Runtime settings (tax, WhatsApp, company, payment) editable without code changes.
- SEO: sitemap.xml, robots.txt, canonical URLs, Open Graph, JSON-LD (Product, Offer,
  AggregateRating, Organization, Article), lazy images, noindex on private pages.
- Queued notifications (in-app now; email/WhatsApp channels are pluggable).

---

## Tech stack

| Layer      | Choice                                            |
|------------|---------------------------------------------------|
| Language   | PHP 8.4                                            |
| Framework  | Laravel 13                                         |
| Database   | MySQL 8+ (production) · SQLite (local/tests)       |
| Views      | Blade + Tailwind CSS v4 + Alpine.js               |
| Build      | Vite (assets precompiled — no Node in production) |
| PDF        | barryvdh/laravel-dompdf                           |

---

## Requirements

- PHP **8.3+** (developed on 8.4) with extensions: `pdo_mysql`, `mbstring`, `openssl`,
  `tokenizer`, `xml`, `ctype`, `json`, `bcmath`/`intl`, `fileinfo`, `gd`, `zip`.
- Composer 2.
- MySQL 8+ (or MariaDB 10.6+). SQLite works for local dev and is used by the test suite.
- Node.js 18+ **only to build assets** (not required at runtime in production).

---

## Local installation

```bash
# 1. Install PHP dependencies
composer install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Database — quickest path uses SQLite
touch database/database.sqlite
# .env already defaults to DB_CONNECTION=sqlite

# 4. Migrate + seed the demo dataset
php artisan migrate:fresh --seed

# 5. Storage symlink (product images, uploads)
php artisan storage:link

# 6. Build frontend assets
npm install
npm run build      # or: npm run dev  (hot reload during development)

# 7. Serve
php artisan serve
```

Visit **http://127.0.0.1:8000**. Admin panel: **/admin** (see demo accounts below).

To use MySQL locally instead, create a database and set `DB_CONNECTION=mysql` plus the
`DB_*` values in `.env`, then re-run step 4.

---

## Demo accounts

Seeded by `UserSeeder` (password for **all** demo accounts is `password`). These are shown
on the login screen when `DEMO_EXPOSE_CREDENTIALS=true`; **set it to `false` in production.**

| Role            | Email                        | Password |
|-----------------|------------------------------|----------|
| Super Admin     | superadmin@rekasurya.test    | password |
| Admin Katalog   | katalog@rekasurya.test       | password |
| Admin Sales     | sales@rekasurya.test         | password |
| Admin Gudang    | gudang@rekasurya.test        | password |
| Admin Keuangan  | keuangan@rekasurya.test      | password |
| Customer        | customer@rekasurya.test      | password |

---

## Production installation (VPS)

```bash
git clone <repo> rekasurya && cd rekasurya
composer install --no-dev --optimize-autoloader
cp .env.example .env
# Edit .env: APP_ENV=production, APP_DEBUG=false, APP_URL=https://your-domain,
#            DB_* (MySQL), SESSION_SECURE_COOKIE=true, DEMO_EXPOSE_CREDENTIALS=false,
#            DEMO_GATEWAY_SECRET / real gateway keys.
php artisan key:generate
php artisan migrate --force
php artisan storage:link

# Build assets on a machine with Node, then deploy public/build/ (no Node needed on server)
npm ci && npm run build

# Cache for performance
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Point your web server's document root at **`public/`**. Example Nginx:

```nginx
server {
    root /var/www/rekasurya/public;
    index index.php;
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

Ensure `storage/` and `bootstrap/cache/` are writable by the web user.

---

## Shared hosting installation

Shared hosting usually cannot set the document root to `public/`. Two options:

**A. Point the domain/subdomain to `public/`** (cPanel → Domains lets you choose a folder).
Upload the whole project outside `public_html` and point the domain to `.../rekasurya/public`.

**B. Move `public/` contents into `public_html`:**

1. Upload the project to e.g. `~/rekasurya` (outside web root) and put the **contents** of
   its `public/` folder into `~/public_html`.
2. Edit `~/public_html/index.php` paths to point at the app:
   ```php
   require __DIR__.'/../rekasurya/vendor/autoload.php';
   $app = require_once __DIR__.'/../rekasurya/bootstrap/app.php';
   ```
3. Build assets locally (`npm run build`) and upload `public/build/` into `~/public_html/build/`.
4. Create the MySQL database in cPanel, set `DB_*` in `.env`.
5. Run migrations via SSH (`php artisan migrate --force --seed`) or the hosting's
   "terminal"/"run command" tool.
6. `php artisan storage:link` (or manually symlink/copy `storage/app/public` →
   `public_html/storage`).
7. Set the scheduler cron (below). Queue can use `QUEUE_CONNECTION=sync` if no worker
   is available.

Because assets are **precompiled**, the production server never needs Node.js.

---

## Database configuration

Production targets **MySQL 8+**. Set in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rekasurya_store
DB_USERNAME=your_user
DB_PASSWORD=your_password
```

Then `php artisan migrate --force` (add `--seed` for demo data). Migrations are written to
run on both MySQL and SQLite; the product FULLTEXT index is created only on MySQL (search
falls back to `LIKE` elsewhere). Money uses `DECIMAL`, never float. See
[`docs/DATABASE.md`](docs/DATABASE.md) for the full schema.

---

## Storage link & uploads

```bash
php artisan storage:link
```

This links `public/storage` → `storage/app/public`. Uploaded product images, banners,
review photos, and quotation attachments are stored there with **randomised filenames**;
uploads are validated by MIME type and size and executables are rejected.

---

## Cron & queue

Add a **single** system cron entry for the Laravel scheduler:

```cron
* * * * * cd /path/to/rekasurya && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler runs `stock:release-expired` every 5 minutes to free stock held by carts
that entered payment but never completed.

Notifications and other deferred work use the queue. Run a worker (VPS):

```bash
php artisan queue:work --tries=3 --timeout=90
```

Keep it alive with **Supervisor** or systemd. On shared hosting without a worker, set
`QUEUE_CONNECTION=sync` so jobs run inline.

---

## Building frontend assets

```bash
npm install
npm run dev      # development, hot module reload
npm run build    # production build -> public/build/ (commit/deploy this)
```

Fonts use a system stack and no external CDN is fetched, so `npm run build` works fully
offline and the production server never needs Node.

---

## Creating an admin user

Via seeder (demo) as above, or interactively with Tinker:

```bash
php artisan tinker
>>> $u = App\Models\User::create([
...   'name' => 'Nama Admin', 'email' => 'admin@domain.com',
...   'password' => 'a-strong-password', 'is_staff' => true, 'is_active' => true,
... ]);
>>> $u->assignRole('super-admin');   // or admin-katalog, admin-sales, admin-gudang, ...
```

Roles & permissions are defined in `app/Support/Rbac.php` and seeded by `RoleSeeder`.
Existing admins can also create staff users at **Admin → User Admin**.

---

## Database backup

```bash
# MySQL
mysqldump -u USER -p rekasurya_store | gzip > backup-$(date +%F).sql.gz

# Restore
gunzip < backup-YYYY-MM-DD.sql.gz | mysql -u USER -p rekasurya_store

# SQLite (dev)
cp database/database.sqlite backups/db-$(date +%F).sqlite
```

Also back up `storage/app/public` (uploaded media). Automate via cron and store off-server.

---

## Testing

```bash
php artisan test
```

The suite (PHPUnit, SQLite in-memory) covers the critical paths: registration/login,
search & slug redirects, cart & stock validation, server-side pricing, coupons, PPN,
volumetric weight, shipping, checkout (recompute + idempotency + reservation), payment
webhook (signature + replay), verified reviews, admin RBAC, quotation→order, plus a
full-render health check of every storefront, admin, and account page.

---

## Security

Implemented throughout (see the webhook model in [`docs/PAYMENTS.md`](docs/PAYMENTS.md)):

- CSRF on all state-changing forms; the payment webhook is CSRF-exempt but
  **HMAC-signature verified** and replay-protected instead.
- Output escaping (Blade `{{ }}`), server-side validation (Form Requests), Eloquent /
  parameter-bound queries only (no raw unparameterised SQL).
- Hashed passwords, rate-limited login/checkout/RFQ, login-activity log, session
  regeneration, secure-cookie option.
- Granular authorization (staff middleware + per-permission gates + ownership checks).
- Mass-assignment protection; internal counters (stock/ratings) are guarded.
- Price/shipping cannot be manipulated from the browser (recomputed server-side).
- Stock guarded against negative values inside DB transactions with row locking.
- File-upload MIME/size validation, randomised filenames, no executables.
- Secrets (API keys, gateway secrets) live only in `.env`, never in source.

---

## Project structure

```
app/
  Enums/            OrderStatus, PaymentStatus, QuotationStatus, ProductCondition, ...
  Http/Controllers/ Storefront, Account/, Admin/, Auth/
  Http/Middleware/  EnsureUserIsStaff, EnsurePermission, SyncGuestSession
  Models/           ~70 Eloquent models
  Notifications/    SystemNotification (queued, multi-channel ready)
  Services/         Cart, Pricing (CartCalculator), Tax, Shipping, Stock, Checkout,
                    Coupon, Quotation, Review, Invoice, Payment (adapters + manager)
  Support/          Rbac, helpers.php
  View/Composers/   StorefrontComposer (shared header/nav data)
database/
  migrations/       ~65 tables
  seeders/          realistic demo data
  factories/        test factories
resources/views/    layouts/, partials/, components/, storefront/, account/, admin/, auth/
routes/web.php      all routes (public, account, admin, webhook)
tests/              Unit + Feature (critical paths + render health)
docs/               DATABASE.md, SHIPPING.md, PAYMENTS.md
```

---

## Further documentation

- [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) — **step-by-step production deployment** (VPS/Nginx, shared hosting/cPanel, SSL, queue, cron, backup, troubleshooting).
- [`docs/DATABASE.md`](docs/DATABASE.md) — database schema & key relationships.
- [`docs/SHIPPING.md`](docs/SHIPPING.md) — volumetric weight & shipping-provider architecture.
- [`docs/PAYMENTS.md`](docs/PAYMENTS.md) — payment adapters, webhook security, integrating a real gateway.
