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
