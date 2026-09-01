# Deployment — Furniture Sales CRM

Target: ordinary shared hosting (cPanel-style) with PHP 8.2+ and MySQL.
No VPS-only infrastructure is required — no websocket server, no Redis, no Docker, and
no permanently running Node.js or queue-worker process.

---

## 1. Requirements

- PHP 8.2 or newer, with the extensions Laravel needs: `bcmath`, `ctype`, `curl`, `dom`,
  `fileinfo`, `json`, `mbstring`, `openssl`, `pcre`, `pdo_mysql`, `tokenizer`, `xml`,
  plus `zip` and `gd` for XLSX export.
- MySQL 5.7+ / MariaDB 10.3+
- Composer (locally is fine — you can upload `vendor/`)
- Node.js **only on your build machine**, never on the server

---

## 2. Build the assets before uploading

Front-end assets are compiled once and shipped as static files.

```bash
npm install
npm run build          # writes public/build/
```

Upload `public/build/` with the rest of the project. The server never runs Node.

---

## 3. Upload layout

On cPanel-style hosting, point the domain's document root at the project's `public/`
directory and keep everything else above it:

```
/home/USERNAME/
├── crm/                 <- application (not web-accessible)
│   ├── app/ bootstrap/ config/ database/ resources/ routes/ storage/ vendor/
│   └── artisan .env
└── public_html/         <- document root -> contents of crm/public/
```

If you cannot move the document root, upload the whole project into `public_html/` and
add a `.htaccess` in the project root that denies access to everything except `public/`.

Permissions:

```bash
chmod -R 775 storage bootstrap/cache
```

---

## 4. Environment

Copy `.env.example` to `.env` and set at minimum:

```env
APP_NAME="Furniture CRM"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
APP_KEY=            # generated below

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_db
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true      # HTTPS only
CACHE_STORE=database
QUEUE_CONNECTION=database

MAIL_MAILER=smtp                # your host's SMTP credentials
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME="${APP_NAME}"

# Password for the seeded accounts on first install. Set it, or the seeder
# generates a random one and prints it to the console once.
SEED_PASSWORD=
```

`APP_DEBUG=false` in production is required: it keeps stack traces off the screen.
`.env` must never be committed to source control.

---

## 5. First install

```bash
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force        # first install only
php artisan storage:link
```

`db:seed` creates the Admin, Manager and Sales Person accounts, the product categories,
the colour master, a sample catalogue, customers and 12 months of orders.

**For a clean production install with no demo data**, seed only the accounts and
reference data:

```bash
php artisan db:seed --class=SettingSeeder --force
php artisan db:seed --class=UserSeeder --force
php artisan db:seed --class=CategorySeeder --force
php artisan db:seed --class=ColourSeeder --force
```

Then sign in as the Admin, change the password immediately, and set the currency under
**Settings → Currency**.

---

## 6. Optimise

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

On Laravel 12 you can run all three (plus the event cache) with:

```bash
php artisan optimize
```

Run these **after** every deployment that changes config, routes or views.

---

## 7. Updating an existing installation

```bash
php artisan down                   # maintenance mode
# upload the new files, including a freshly built public/build/
composer install --no-dev --optimize-autoloader
php artisan migrate --force        # never migrate:fresh on production
php artisan optimize:clear
php artisan optimize
php artisan up
```

`migrate:fresh` drops every table. Only ever use it locally.

---

## 8. Scheduled tasks

Everything scheduled runs from a single cron entry. In cPanel → Cron Jobs, add one job
running **every minute**:

```bash
php /home/USERNAME/crm/artisan schedule:run >> /dev/null 2>&1
```

Adjust the path and, if your host requires it, the full PHP binary path
(for example `/usr/local/bin/php8.2`).

`routes/console.php` currently schedules:

- `queue:work --stop-when-empty --max-time=55` every minute — drains any queued work and
  exits, so nothing stays resident between ticks.
- `auth:clear-resets` daily — clears expired password-reset tokens.

No supervisor or long-running worker is needed.

---

## 9. Backups

Set up a **daily MySQL backup** in your hosting control panel and keep several
generations (7 daily plus 4 weekly is a reasonable baseline).

Manual dump and restore:

```bash
# Backup
mysqldump -u USER -p DATABASE > crm-backup-$(date +%F).sql

# Restore
mysql -u USER -p DATABASE < crm-backup-2026-09-01.sql
```

Also back up `.env` and `storage/app/public/` (uploaded profile photos).

**Test a restore periodically** — a backup you have never restored is not a backup.
Restore into a scratch database and confirm the order count and totals match.

---

## 10. Security checklist

- [ ] `APP_DEBUG=false` and `APP_ENV=production`
- [ ] `APP_KEY` generated
- [ ] HTTPS enforced, `SESSION_SECURE_COOKIE=true`
- [ ] Document root points at `public/`, so `.env`, `storage/` and `vendor/` are not web-reachable
- [ ] Seeded passwords changed; `SEED_PASSWORD` removed from `.env` afterwards
- [ ] Database user has only the privileges it needs on its own database
- [ ] Daily backups configured and a restore tested
- [ ] `storage/logs/` monitored and rotated

---

## 11. Health check

The application exposes `/up`. Point your uptime monitor at
`https://your-domain.com/up` — it returns 200 when the framework boots cleanly.

---

## 12. Troubleshooting

| Symptom | Cause and fix |
|---|---|
| 500 with a blank page | Read `storage/logs/laravel.log`. Usually `storage/` permissions or a missing `APP_KEY`. |
| Styles missing / unstyled page | `public/build/` was not uploaded. Run `npm run build` locally and upload it. |
| "Vite manifest not found" | Same cause as above. |
| Routes 404 after deploying | Stale caches — run `php artisan optimize:clear`, then `php artisan optimize`. |
| Reports empty but orders exist | Check the period filter; it defaults to the current month. |
| Amounts show no currency symbol | Expected until an Admin sets one in **Settings → Currency**. |
| Password reset emails not arriving | `MAIL_*` not configured. An Admin can set passwords directly under **Users → Edit**. |
