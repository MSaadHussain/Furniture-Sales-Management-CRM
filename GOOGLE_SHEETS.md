# Google Sheets Mirror

Orders, customers and products are mirrored into a Google Sheet. Existing
history is backfilled on the first run, and every later change follows on its
own.

Spreadsheet in use:
`https://docs.google.com/spreadsheets/d/1FgyHqjTH4Qabf94cSI_hv7-b5JQLv6XAZxj0UwSuP5I/edit`

---

## How it works

Shared hosting allows no permanently running process, so the sync is not
inline with the request that saves an order.

1. **Observers flag rows.** Saving, deleting or restoring an order, customer or
   product sets that row's `sheet_synced_at` back to `NULL` — one cheap UPDATE,
   no HTTP call. Changing a line item flags its parent order, because the items
   appear in the order's row.
2. **Cron drains the flags.** `sheets:sync` runs on the existing
   `schedule:run` tick, collects the flagged rows and posts them to an Apps
   Script Web App that owns the spreadsheet. Nothing flagged means no HTTP call
   at all.
3. **Rows are stamped only on success.** A failed or unreachable Google leaves
   the rows flagged, so the next tick simply retries. Nothing is lost and
   nothing is written twice.

**Backfill is free.** The migration adds `sheet_synced_at` as `NULL` for every
existing row, so the first run pushes the entire history without a separate
import step.

**Upsert, not append.** Column A of each tab holds the CRM row id. The script
matches on it, so editing an order rewrites its existing line instead of adding
a second one — even if you have sorted or filtered the sheet by hand. Deleting
an order removes its row.

**The URL is public, so requests are signed.** Each request carries
`HMAC-SHA256("<timestamp>.<payload>")` and the script refuses anything older
than five minutes. Apps Script does not pass request headers to `doPost`, so the
signature travels inside the body and the payload is sent as an already encoded
string — both sides then sign byte-for-byte identical text.

---

## Setup

### 1. Install the script

1. Open the spreadsheet → **Extensions → Apps Script**.
2. Delete whatever is in `Code.gs` and paste the whole of
   [`google-apps-script/Code.gs`](google-apps-script/Code.gs).
3. Set `SHARED_SECRET` at the top to the same value as `GOOGLE_SHEETS_SYNC_SECRET`
   in `.env`. (Alternatively leave it and put the secret in **Project Settings →
   Script Properties** under the key `SHARED_SECRET`; that wins over the source.)
4. Save.

### 2. Deploy it

**Deploy → New deployment → Web app**, then:

| Field           | Value                     |
|-----------------|---------------------------|
| Execute as      | **Me**                    |
| Who has access  | **Anyone**                |

Authorise when prompted (the "unverified app" warning is expected for your own
script — *Advanced → Go to project*). Copy the `…/exec` URL.

> "Anyone" is what lets the CRM reach the script without an OAuth dance. The
> shared secret is what keeps strangers out, so treat it like a password.

### 3. Point the CRM at it

```dotenv
GOOGLE_SHEETS_ENABLED=true
GOOGLE_SHEETS_SYNC_URL=https://script.google.com/macros/s/.../exec
GOOGLE_SHEETS_SYNC_SECRET=<the same secret as in Code.gs>
```

Then:

```bash
php artisan config:clear
php artisan sheets:status   # connection check + what is still pending
php artisan sheets:sync     # first run: pushes all existing data
```

**Every time you redeploy the script**, use *Deploy → Manage deployments →
edit → Version: New version*. Creating a brand new deployment gives you a
different URL, which then has to go back into `.env`.

### 4. Leave it running

`routes/console.php` already schedules `sheets:sync` every minute, driven by
the one cPanel cron entry that runs `php artisan schedule:run`. No extra cron
is needed.

---

## Commands

| Command                       | Purpose |
|-------------------------------|---------|
| `sheets:status`               | Is it configured, does the connection work, how many rows are pending |
| `sheets:sync`                 | Push everything flagged (what cron runs) |
| `sheets:sync --all`           | Re-flag every row and rewrite the whole sheet |
| `sheets:sync --limit=50`      | Smaller batches, for a slow connection |

---

## Tabs and columns

Tabs are created automatically. **Do not reorder or delete column A** — the id
is how rows are matched. Adding your own extra columns to the right is safe;
the sync only writes the columns it owns.

| Tab       | Columns |
|-----------|---------|
| Orders    | ID, Order No, Order Date, Customer, Phone, Address, ZIP, Items, No. of Orders, Sales Person, Source, Delivery Date, Delivered On, Order Status, Payment Status, Subtotal, Discount, Delivery Charge, Tax, Grand Total, Amount Paid, Balance Due, Notes |
| Customers | ID, Name, Phone, Alt Phone, Email, Address, City, State, ZIP, Orders, Notes, Added On |
| Products  | ID, Code, Name, Category, Colours, Default Price, Active, Added On |

Money is written as plain numbers, not formatted with a currency symbol, so the
sheet can total and chart it.

**The sheet is a mirror, not a second source of truth.** Editing a cell in
Google does not come back to the CRM, and the next time that row changes your
edit is overwritten.

---

## Troubleshooting

| Symptom | Cause |
|---------|-------|
| `Apps Script did not return JSON` | Deployment is not set to "Anyone" access — it is serving a Google login page |
| `Bad signature` | `SHARED_SECRET` in the script and `GOOGLE_SHEETS_SYNC_SECRET` in `.env` differ |
| `Request timestamp expired` | Server clock is more than 5 minutes off |
| `sync is off` | One of the three env values is missing, or `config:clear` was not run |
| Rows pending but never sent | Cron is not running `schedule:run`; check `php artisan schedule:list` |
| Duplicate rows | Column A was edited or removed by hand. `php artisan sheets:sync --all` after clearing the tab fixes it |

Failures are logged to `storage/logs/laravel.log` with the `Google Sheets sync failed` prefix.
