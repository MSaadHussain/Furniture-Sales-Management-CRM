# Furniture Sales CRM

A web CRM for a furniture retailer: sales orders, customers, products, delivery
operations, and location-based sales intelligence built around customer ZIP/postal codes.

Laravel 12 · PHP 8.2+ · MySQL · Blade + Alpine.js · Tailwind CSS.

---

## 1. Getting it running

You need PHP 8.2+, Composer, MySQL and Node.js.

```bash
# 1. Install dependencies
composer install
npm install

# 2. Configure the environment
cp .env.example .env
php artisan key:generate
```

Open `.env` and point it at your database:

```env
APP_NAME="Furniture CRM"
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=crmapp
DB_USERNAME=root
DB_PASSWORD=
```

Create the database, then build the schema and sample data:

```bash
php artisan migrate:fresh --seed
npm run build
php artisan serve
```

Open **http://localhost:8000** — you will land on the login screen.

> `migrate:fresh --seed` **drops everything** and rebuilds. On a live system use
> `php artisan migrate` instead.

---

## 2. Logging in

There is no public sign-up page. Every account is created by an Admin from
**Users & Sales Persons**. The seeder creates these accounts for local development:

| Role         | Email                  | Password   | What they see |
|--------------|------------------------|------------|---------------|
| Admin        | `admin@example.test`   | `password` | Everything |
| Manager      | `manager@example.test` | `password` | Sales, deliveries, customers, reports |
| Sales Person | `ali@example.test`     | `password` | Dashboard only |
| Sales Person | `ahmed@example.test`   | `password` | Dashboard only |
| Sales Person | `sara@example.test`    | `password` | Dashboard only |
| Sales Person | `bilal@example.test`   | `password` | Dashboard only |

**These passwords only exist in `local` and `testing`.** In any other environment the
seeder reads `SEED_PASSWORD` from `.env`, or generates a random password and prints it
to the console once. Change it immediately after the first login.

### Forgot password

Click **Forgot password?** on the login screen. The reset link is emailed, so configure
your `MAIL_*` settings first (SMTP credentials from your host). Until mail is
configured, an Admin can set a new password directly from **Users → Edit**.

### Session behaviour

- **Idle logout** — after 15 minutes of inactivity a warning appears, then you are
  signed out. Configure or disable it in **Settings → Security**.
- **Daily password re-confirmation** — once every 24 hours you are asked to re-enter your
  password. This does not sign you out. Also toggleable in **Settings → Security**.
- **Login rate limiting** — 5 attempts per minute per email, 30 per minute per IP address.

### Changing your own details

Click your name in the top-right corner → **Edit profile**. You can change your name,
email, phone, profile photo and password there. You cannot delete your own account or
change your own role; ask an Admin.

---

## 3. The three roles

### Admin
Full control. Creates users, manages products and categories, edits and cancels orders,
changes settings, and reads the audit log.

### Manager
Runs day-to-day operations: creating and editing orders, working the delivery board,
viewing customers and products, and reading every report.

A Manager does **not** get destructive or system-level rights by default. An Admin can
grant these individually in **Settings → Manager permissions**:

- Cancel orders
- Create and edit customers
- Manage products, categories and colours
- Export reports and data *(on by default)*

### Sales Person
Registered by an Admin, selectable as the seller on an order, and given a **read-only
dashboard** — business totals, the sales trend, top products and popular colours. They
cannot open orders, the customer list, reports or any admin screen. The restriction is
enforced in the backend, not just by hiding menu items.

---

## 4. Day-to-day use

### Creating an order

**Sales → Add New Order.** The form is one page in four parts:

1. **Customer** — start typing in *Find an existing customer* to reuse someone.
   If you type a phone number that already exists, a banner offers the matching
   customer along with their order count and lifetime spend, so you never create a
   duplicate record. Name, phone and **ZIP/postal code** are required — the ZIP is what
   powers the location reports.
2. **Furniture items** — press **+ Add Another Item** for each piece. Picking a product
   fills in its name, default price and available colours; you can still override the
   price or type a custom item. Line totals update as you type.
3. **Dates** — the order creation date is stamped automatically. You choose the
   **customer requested delivery date** (today or later).
4. **Totals & payment** — order discount, delivery charge and tax feed the grand total.
   Pick a payment status; *Partial* reveals an Amount Paid field.

On save you get the generated order number, for example
`Order SALE-2026-000125 created successfully.`

All money is recalculated on the server. Whatever the browser displays, the saved
figures come from the line items.

### Moving an order along

Open the order and use the **Status** card:

```
New → Confirmed → Processing → Ready for Delivery → Out for Delivery → Delivered
```

`Cancelled`, `Delayed` and `Returned` are exception states. Order status and payment
status are independent — an order can be *Ready for Delivery* and *Paid*, or *Delivered*
and *Pending*.

Cancelling asks for a reason and freezes the order: no more edits, and it stops counting
towards revenue. This applies to Admins too.

### Deliveries

**Deliveries → Today & Daily Board** opens on today's scheduled deliveries with a
breakdown by status, the total value, and a one-click **✓** to mark an order delivered
today. Use the date field or the arrows to look at any other day.

**Deliveries → Delivery Calendar** shows a month grid with the delivery count under each
date; click a date to jump to its board.

Recording an actual delivery date **never** overwrites the customer's requested date.
The difference between the two is what drives the on-time delivery rate.

### Customers

**Customers** lists everyone with their order count, lifetime spend and last order date.
Search by name, phone, email, ZIP or customer ID. A customer's profile shows their full
order history, the products they have bought before, and delivery history. A customer
with orders cannot be deleted — historical sales data stays intact.

### Products, categories and colours

**Products** is the catalogue used by the order form. Each product has a code (generated
if you leave it blank), a category, a default price and an optional colour shortlist —
leave the shortlist empty and the product offers every active colour.

**Categories** and **Colours** are edited inline on their own screens. Anything with
history is deactivated rather than deleted, so past orders and reports stay correct.

---

## 5. Reports and sales intelligence

Every report shares the same period filter: Today, Yesterday, Last 7 days, This week,
This month, Last month, Last 3/6 months, This year, or a custom range. Each one exports
to CSV and Excel.

| Report          | Answers |
|-----------------|---------|
| Sales           | Every order in the period, filterable by sales person, product, category, ZIP, order status and payment status |
| Product         | Top sellers by units and revenue, category performance, colour demand |
| Customer        | New vs returning, average orders and spend, top customers, ZIP distribution |
| **ZIP Code**    | Orders, revenue, average order value, share and growth per area, plus a per-area product mix and a list of areas that have gone quiet |
| Sales Person    | Orders, revenue and average order value per seller |
| Delivery        | Requested vs actual dates, on-time/late flags and the on-time rate |

The dashboard also surfaces **marketing opportunities** in plain language — the leading
area, the fastest-growing area, the top product and the most-requested colour. It is
decision support: the system reports, it never spends money or launches campaigns.

Growth always compares against the equivalent preceding period. Where there is no
baseline to compare against, it reads **N/A** rather than an invented percentage.

ZIP reporting is aggregated. The ZIP export contains area totals only — no customer
names, phone numbers or addresses ever leave in it.

---

## 6. Currency

Currency has **no default**. Until an Admin sets it, amounts render as plain numbers.
Go to **Settings → Currency** and set the symbol (`Rs.`, `$`, …), the number of decimal
places and whether the symbol sits before or after the amount. A live preview shows the
result. The setting applies everywhere, including exports.

---

## 7. Audit log

**Audit Logs** (Admin only) records who did what, to which record, when and from which
IP address: logins, order creation and edits, status and payment changes, delivery-date
changes, recorded deliveries, product and user changes, and settings updates.

Entries that carry a before/after snapshot have a **⇄** icon opening a field-level diff.
Passwords and tokens are never written to the audit trail.

**Settings → User Activity** and **Time Spent** show per-user page activity and session
durations.

---

## 8. Running the tests

```bash
php artisan test
```

The suite runs against a separate MySQL database (`crmapp_test` by default — see
`phpunit.xml`) rather than SQLite, because the reports use MySQL functions such as
`DATE_FORMAT`, `FIELD` and `DATEDIFF`. Create it once:

```sql
CREATE DATABASE crmapp_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

---

## 9. Deployment

The application is designed for ordinary shared hosting: no queue daemon, websocket
server, Redis, Docker or permanently running Node process. See **[DEPLOYMENT.md](DEPLOYMENT.md)**.

---

## 10. Where things live

```
app/
├── Enums/          UserRole, OrderStatus, PaymentStatus, PaymentMethod, DeliveryPerformance
├── Http/
│   ├── Controllers/  one per module
│   ├── Requests/     validation rules
│   └── Middleware/   role check, idle/24h password, activity tracking
├── Models/         Order, OrderItem, Customer, Product, Category, Colour, User, Setting, AuditLog
├── Policies/       record-level rules
├── Services/       order, delivery, analytics, ZIP intelligence, audit, export
└── Support/        Money formatting

resources/views/   dashboard, orders, customers, products, deliveries, reports, users, settings
database/          migrations, seeders, factories
```

Developer-facing notes, conventions and the requirement cross-references are in
**CLAUDE.md**.
