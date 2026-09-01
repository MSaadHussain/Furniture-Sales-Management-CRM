# Furniture Sales CRM — Project Reference

Laravel 12 sales, delivery and location-intelligence CRM for a furniture retailer.
PHP 8.2+ · MySQL · Blade + Alpine.js · Tailwind CSS 3 · Vite · Laravel Breeze auth.

Built against `furniture_crm_requirements.md`. Section numbers referenced in code
comments (for example "requirements 14.4") point at that document.

## Quick Commands

```bash
composer install
php artisan migrate:fresh --seed   # full reset with 12 months of sample data
php artisan migrate                # incremental
npm install && npm run build       # frontend assets
php artisan serve                  # http://localhost:8000
php artisan test                   # 103 tests
```

## Seeded Accounts (local only)

| Role         | Email                  | Password   |
|--------------|------------------------|------------|
| Admin        | admin@example.test     | `password` |
| Manager      | manager@example.test   | `password` |
| Sales Person | ali@example.test       | `password` |
| Sales Person | ahmed@example.test     | `password` |
| Sales Person | sara@example.test      | `password` |
| Sales Person | bilal@example.test     | `password` |

Outside `local`/`testing` the seeder uses `SEED_PASSWORD`, or generates a random
password and prints it once — production never inherits a known default.

---

## Roles (app/Enums/UserRole.php)

Three roles only:

- **Admin** — full control: orders, customers, catalogue, users, settings, audit logs.
- **Manager** — day-to-day operations: orders, deliveries, customers (read), catalogue
  (read), all reports. Destructive/system actions are **off** until an Admin grants
  them in Settings.
- **Sales Person** — read-only dashboard, nothing else. Selectable as the seller on an order.

### Permission model

Capability **gates** are defined in `AppServiceProvider::defineGates()`; record-level
rules live in **policies** (`OrderPolicy`, `CustomerPolicy`, `ProductPolicy`).

`Gate::before` grants Admins every *capability* gate but **deliberately defers when the
check carries a model instance**, so record-level rules (a cancelled order is frozen)
apply to Admins too.

Manager extras, stored in `settings` and edited on the Settings screen:
`manager_can_cancel_orders`, `manager_can_manage_customers`,
`manager_can_manage_products`, `manager_can_export_data` (the last defaults on).

Enforcement is server-side on every route: controllers declare
`public static function middleware(): array` (Laravel 11+ `HasMiddleware`).

---

## Database

| Table                | Migration                                             | Notes |
|----------------------|-------------------------------------------------------|-------|
| users                | `2026_09_01_000001_add_role_fields_to_users_table`     | role, phone, is_active, soft deletes |
| customers            | `..._000002_create_customers_table`                    | phone + zip_code indexed; soft deletes |
| categories           | `..._000003_create_categories_table`                   | unique name + slug |
| colours              | `..._000004_create_colours_table`                      | colour master |
| products             | `..._000005_create_products_table`                     | unique product_code; soft deletes |
| product_colour       | `..._000006_create_product_colour_table`               | optional per-product colour shortlist |
| orders               | `..._000007_create_orders_table`                       | see below |
| order_items          | `..._000008_create_order_items_table`                  | snapshot columns |
| audit_logs           | `..._000009_create_audit_logs_table`                   | old_values / new_values JSON |
| settings             | `..._000010_create_settings_table`                     | key/value JSON |
| user_sessions        | `..._000011_...`                                       | time-spent tracking |
| user_activity_logs   | `..._000012_...`                                       | page/action tracking |

### The three order dates (non-negotiable)

`orders` keeps them in separate columns and one never overwrites another:

- `order_created_at` — stamped by the server when the order is saved.
- `requested_delivery_date` — what the customer asked for.
- `actual_delivery_date` — filled in on completion; recording it never touches the requested date.

`orders.zip_code` is a denormalised copy of the customer ZIP at order time, so a customer
moving house does not rewrite past location analytics.

### Snapshots

`order_items` stores `item_name_snapshot`, `item_colour` and `category_name_snapshot`,
so renaming or deleting a product/colour cannot corrupt historical reporting.

### Indexes

`orders`: order_number (unique), customer_id, sales_person_id, order_created_at,
requested_delivery_date, actual_delivery_date, order_status, payment_status, zip_code,
plus a composite `(requested_delivery_date, order_status)` for the daily delivery board.

---

## Enums (app/Enums/)

| Enum                | Values |
|---------------------|--------|
| UserRole            | admin, manager, sales_person |
| OrderStatus         | new, confirmed, processing, ready_for_delivery, out_for_delivery, delivered, cancelled, delayed, returned |
| PaymentStatus       | pending, partial, paid, refunded |
| PaymentMethod       | cash, bank_transfer, card, online, other |
| DeliveryPerformance | on_time, late, pending (derived, never stored) |

`OrderStatus::revenueValues()` excludes cancelled + returned — every revenue aggregate
uses the `Order::scopeCountable()` built on it. `OrderStatus::openValues()` drives the
delivery board and overdue counts.

---

## Services (app/Services/)

| Service                | Purpose |
|------------------------|---------|
| OrderService           | Create/update orders, recalculate every total server-side, resolve or reuse the customer, sync snapshotted items, status/payment/delivery/cancel transitions |
| OrderNumberService     | `SALE-2026-000001`; sequential per year, locked, never reused (includes soft-deleted orders) |
| DeliveryService        | Daily board, month calendar counts, on-time performance, overdue/pending counts, 7-day outlook |
| SalesAnalyticsService  | KPIs with growth, trend buckets (day/week/month), top products, categories, colour demand, sales-person performance, customer stats |
| ZipAnalyticsService    | ZIP ranking with share + growth, quiet areas, per-ZIP product mix, marketing insights |
| DateRangeService       | The 10 date presets, previous-period resolution (whole month → previous whole month), growth maths with `N/A` on a zero baseline |
| AuditService           | Audit trail with before/after diffing and redaction of secrets |
| ActivityTrackingService| Session time and page activity |
| Export\TabularExport   | Streaming CSV / XLSX |

`app/Support/Money.php` formats every amount from the currency settings. There is **no
built-in default** — amounts render as bare numbers until an Admin picks a symbol.

---

## Routes & Controllers

| Prefix                | Controller               | Notes |
|-----------------------|--------------------------|-------|
| `/dashboard`          | DashboardController      | Branches: management view vs restricted Sales Person view |
| `/orders`             | OrderController          | Resource + status/payment/deliver/cancel + JSON lookups + export |
| `/customers`          | CustomerController       | Resource + export |
| `/products`           | ProductController        | Resource + toggle + export |
| `/categories`         | CategoryController       | index/store/update/destroy (inline editing) |
| `/colours`            | ColourController         | index/store/update/destroy (inline editing) |
| `/deliveries`         | DeliveryController       | Daily board (defaults to today), calendar, export |
| `/reports/*`          | ReportController         | sales, products, customers, zip, sales-persons, deliveries + `/{report}/export` |
| `/users`              | UserController           | Admin only; last-active-admin guard |
| `/audit-logs`         | AuditLogController       | Admin only; field-level diff view + export |
| `/settings`           | SettingsController       | Currency, business name, manager permissions, defaults |
| `/settings/security`  | SecurityController       | Idle logout, 24h password re-confirmation |
| `/activity/*`         | ActivityController       | Admin only |
| `/profile`            | ProfileController        | Self-service; no self-deletion route |

Fixed paths (`orders/export`, `customers/export`, `products/export`) are declared
**before** the resource route so the `{id}` wildcard does not swallow them.

`routes/api.php` is intentionally empty — there is no public API surface.
Public self-registration is disabled; Admins create every account.

---

## Views (resources/views/)

`layouts/app.blade.php` (TailAdmin theme) is the only authenticated layout;
`layouts/guest.blade.php` covers the auth screens.

| Directory     | Screens |
|---------------|---------|
| dashboard/    | index (management), sales-person (restricted) |
| orders/       | index, create, edit, `_form`, show |
| customers/    | index, create, edit, `_form`, show |
| products/     | index, create, edit, `_form`, show |
| categories/   | index (inline CRUD) |
| colours/      | index (inline CRUD) |
| deliveries/   | index (daily board), calendar |
| reports/      | sales, products, customers, zip, sales-persons, deliveries, `_nav` |
| audit/        | index, show |
| users/        | index, create, edit, `_form` |
| settings/     | index, security |
| activity/     | logs, sessions |

### Shared components

`x-money`, `x-growth`, `x-status-pill`, `x-date-range`, `x-empty-state`,
`x-export-menu`, `x-card`, `x-stat-card`, `x-badge`, `x-user-avatar`.

### The order form (`orders/_form.blade.php`)

One Alpine component (`orderForm()`) handles: customer typeahead, phone-based duplicate
detection, repeatable item rows with per-product colour narrowing, live line/grand
totals, and an unsaved-changes guard. Server-side totals are authoritative — the client
figures are display only. Data reaches Alpine via `Js::from()`.

---

## Key Conventions

- **Server recalculates money.** `OrderService::applyTotals()` ignores any client total.
  `Grand Total = Subtotal - Discount + Delivery Charge + Tax`.
- **Payment status drives amount paid.** Paid → full, Pending/Refunded → 0,
  Partial → the entered amount, clamped to the grand total.
- **Cancelled and returned orders are frozen** for everyone, Admins included.
- **Products/categories with history are deactivated, not deleted.**
- **Aggregate SQL only.** Reports never pull rows into PHP to count them; filtering and
  pagination are server-side.
- **Flash messages**: `session('toast')` for success, `session('error')` for failures.
- **CSS classes**: `ta-card`, `ta-input`, `ta-label`, `ta-th`, `ta-td`, `ta-nav-link`,
  `ta-nav-sub`, `ta-badge`, `btn btn-primary`, `btn btn-light`, `btn btn-danger`.

---

## Tests (tests/Feature/)

| File                      | Covers |
|---------------------------|--------|
| OrderManagementTest       | Creation, server-side totals, order numbering, validation rules, duplicate customers, the three dates, snapshots, cancellation, audit |
| RolePermissionTest        | The whole role matrix, backend-enforced, plus the Manager permission switches |
| DeliveryOperationsTest    | Daily board, calendar, on-time/late classification, on-time rate, overdue counts |
| SalesIntelligenceTest     | ZIP ranking/share/growth, product + colour + sales-person analytics, all six reports and their exports, privacy of the ZIP export |
| CatalogueTest             | Customers, products, categories, colours |
| UserManagementTest        | Registration, hashing, active-seller rule, last-admin guard |
| ProfileTest, Auth/*       | Self-service profile and Breeze auth |

The suite runs against **MySQL** (`crmapp_test`), not SQLite, because the reports use
`DATE_FORMAT`, `FIELD`, `DATEDIFF` and `GREATEST`. See `phpunit.xml`.

---

## Deployment (shared hosting)

No queue daemon, websocket, Redis, Docker or permanently running Node process is
required. See `DEPLOYMENT.md`.
