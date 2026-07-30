# HABB POS — Laravel API backend

This is the backend for the `habb-pos.html` frontend: products, categories,
held tickets, and completed sales, all persisted in a database instead of
living only in the browser tab.

It's shipped as a set of files to drop into a fresh Laravel install (models,
migrations, controllers, routes, seeder) rather than a zipped `vendor/`
folder — that keeps it small and lets `composer` pull framework code your
own machine already trusts.

## 1. Create the Laravel app

```bash
composer create-project laravel/laravel habb-pos-backend
cd habb-pos-backend
```

Any recent Laravel version (10, 11, or 12) works with the files here.

## 2. Copy these files in

Copy everything from this package into the matching paths in your new
Laravel project, overwriting where they already exist:

```
app/Models/Category.php
app/Models/Product.php
app/Models/Order.php
app/Models/OrderItem.php
app/Http/Controllers/Api/BusinessTypeController.php
app/Http/Controllers/Api/CategoryController.php
app/Http/Controllers/Api/ProductController.php
app/Http/Controllers/Api/OrderController.php
app/Http/Resources/ProductResource.php
app/Http/Resources/CategoryResource.php
app/Http/Resources/OrderResource.php
database/migrations/2026_07_30_000001_create_categories_table.php
database/migrations/2026_07_30_000002_create_products_table.php
database/migrations/2026_07_30_000003_create_orders_table.php
database/migrations/2026_07_30_000004_create_order_items_table.php
database/seeders/HabbPosSeeder.php
database/seeders/DatabaseSeeder.php
routes/api.php
```

**Laravel 11/12 note:** `routes/api.php` isn't registered by default in a
fresh install. Add this in `bootstrap/app.php`:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```

**Laravel 10 note:** `routes/api.php` is already wired up via
`RouteServiceProvider` — nothing extra to do.

## 3. Database

Simplest path — SQLite, zero config:

```bash
touch database/database.sqlite
```

In `.env`, set:
```
DB_CONNECTION=sqlite
```
(remove/comment out the other `DB_*` lines)

To use MySQL/Postgres instead, set the usual `DB_*` values in `.env` — the
migrations don't use anything SQLite-specific.

## 4. Migrate and seed

```bash
php artisan migrate --seed
```

This loads the same retail/café/service catalog already hardcoded in
`habb-pos.html`, so the two line up immediately.

## 5. CORS

If the frontend is opened as a local file or served from a different origin
than the API, allow it in `config/cors.php`:

```php
'paths' => ['api/*'],
'allowed_origins' => ['*'], // tighten to your actual origin before going live
```

## 6. Run it

```bash
php artisan serve
```

API is now at `http://localhost:8000/api/v1`.

---

## API reference

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/business-types` | List retail/café/service, with label + icon |
| GET | `/categories?business_type=retail` | Categories for a mode |
| GET | `/products?business_type=retail&category=drinks&search=tea` | Catalog, filterable |
| POST | `/orders` | Create a ticket (`status: "held"` or `"completed"`) |
| GET | `/orders?status=held&business_type=retail` | List tickets (held drawer / history) |
| GET | `/orders/{id}` | Fetch one ticket (e.g. for a receipt) |
| PATCH | `/orders/{id}` | Replace items/discount on a **held** ticket |
| DELETE | `/orders/{id}` | Cancel a **held** ticket |
| POST | `/orders/{id}/complete` | Take payment on a held ticket (Charge button) |

**Money is always computed server-side.** The client sends `product_id` +
`qty`; the API looks up the current price, snapshots it onto the order line,
and recalculates subtotal/discount/tax/total itself. This means a stale
price in the browser tab can never under- or over-charge a customer.

### Example: create + immediately charge

```json
POST /api/v1/orders
{
  "business_type": "cafe",
  "status": "completed",
  "discount_percent": 0,
  "items": [
    { "product_id": 1, "qty": 2 },
    { "product_id": 6, "qty": 1 }
  ],
  "payment_method": "Cash",
  "tendered": 20
}
```

Response includes `total`, `tendered`, `change_due`, and the snapshotted
`items[]` — everything needed to render a receipt.

### Example: hold, then charge later

```json
POST /api/v1/orders
{ "business_type": "retail", "items": [{ "product_id": 3, "qty": 1 }] }
```

→ returns `{ "id": 42, "status": "held", ... }`

```json
POST /api/v1/orders/42/complete
{ "payment_method": "Card" }
```

## Wiring up habb-pos.html (next step)

Right now the frontend's `CATALOG` object and `state.cart` logic are all
client-side. To connect it to this API:

1. Replace the hardcoded `CATALOG` with a `fetch('/api/v1/products?...')`
   call in `renderProducts()` / `renderCategories()`.
2. On `holdOrder()`, `POST /orders` with `status: "held"` instead of just
   pushing to a local array.
3. On `completeSale()`, `POST /orders` (or `/orders/{id}/complete` if it
   was already held) instead of computing totals in JS, and render the
   stamp using the response's `total`/`change_due`.

Happy to do that wiring next if you want the frontend actually talking to
this instead of running on the hardcoded catalog.

---

## Deploying to Vercel

Vercel doesn't run PHP natively — this uses the community
[`vercel-php`](https://github.com/vercel-community/php) runtime, which
runs Laravel as a single serverless function. `api/index.php`,
`vercel.json`, and `.vercelignore` in this folder are already set up for
it. Before deploying, understand the tradeoffs:

- **No persistent disk.** SQLite is out — point `DB_CONNECTION` at an
  external database (PlanetScale/MySQL, Neon/Supabase Postgres, Turso).
- **No cron or queue workers.** `artisan schedule:run` and queued jobs
  won't run on Vercel; skip them or run them elsewhere.
- **Sessions/cache** are forced to `cookie` / `array` in `vercel.json`
  since there's no shared disk between invocations.
- **Migrations don't run on deploy.** Run `php artisan migrate --force`
  against the external DB yourself (locally, or via a GitHub Actions step)
  before or after each deploy — Vercel's build step only runs
  `composer install`.

### Steps

1. Provision an external database and note its connection details.
2. Generate an app key locally: `php artisan key:generate --show`
3. Install the Vercel CLI and log in:
   ```bash
   npm i -g vercel
   vercel login
   ```
4. From this `backend/` folder, run `vercel` and accept the defaults on
   first deploy (it'll link/create a Vercel project).
5. In the Vercel dashboard for that project, add environment variables:
   `APP_KEY`, `APP_URL`, `DB_CONNECTION`, `DB_HOST`, `DB_PORT`,
   `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (matching whatever database
   you provisioned in step 1).
6. Run migrations against that database from your machine:
   ```bash
   php artisan migrate --seed --force
   ```
7. Redeploy: `vercel --prod`.

From then on, connecting GitHub in the Vercel dashboard gives you
automatic deploys on push — just remember migrations still need to be run
separately whenever the schema changes, since Vercel itself won't do it.

