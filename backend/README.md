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
Laravel project, overwriting where they already exist — see the file list
in the repo's `backend/` folder.

**Install Sanctum** (used for login tokens):
```bash
composer require laravel/sanctum
php artisan install:api
```
(On Laravel 10, use `php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"` instead of `install:api`, then run `php artisan migrate` to create the `personal_access_tokens` table.)

**Register the `role` middleware** — in `bootstrap/app.php` (Laravel 11/12):
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias(['role' => \App\Http\Middleware\EnsureRole::class]);
})
```
On Laravel 10, add it to the `$routeMiddleware` array in `app/Http/Kernel.php` instead:
```php
'role' => \App\Http\Middleware\EnsureRole::class,
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

**Production (matches HABB Stay's setup):** MySQL, hosted alongside the
API on the same cPanel account. See "Deploying to a PHP host" below for
the full production setup.

For local development, either point at a local MySQL instance:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=habbpos
DB_USERNAME=root
DB_PASSWORD=
```
or use SQLite for zero-config local testing:
```bash
touch database/database.sqlite
```
```
DB_CONNECTION=sqlite
```
(remove/comment out the other `DB_*` lines)

The migrations don't use anything database-specific, so switching between
them is just an `.env` change + re-running migrations.

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

## Authentication

All endpoints except `POST /auth/login` require a Sanctum bearer token:

```
Authorization: Bearer <token>
```

Get one by logging in with a seeded account (`admin@habb.lk` /
`cashier@habb.lk`, password `password` — change these before production):

```json
POST /api/v1/auth/login
{ "email": "admin@habb.lk", "password": "password" }
```
→ `{ "token": "...", "user": { "id": 1, "name": "...", "role": "admin" } }`

Endpoints under **Admin only** below additionally require the logged-in
user's `role` to be `admin` — a cashier token gets a 403.

## API reference

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/auth/login` | Log in, get a bearer token |
| POST | `/auth/logout` | Revoke the current token |
| GET | `/auth/me` | Current user + role |
| GET | `/business-types` | List retail/café/service, with label + icon |
| GET | `/categories?business_type=retail` | Categories for a mode |
| GET | `/products?business_type=retail&category=drinks&search=tea` | Catalog, filterable |
| POST | `/orders` | Create a ticket (`status: "held"` or `"completed"`) |
| GET | `/orders?status=held&business_type=retail` | List tickets (held drawer / history) |
| GET | `/orders/{id}` | Fetch one ticket (e.g. for a receipt) |
| PATCH | `/orders/{id}` | Replace items/discount/customer on a **held** ticket |
| DELETE | `/orders/{id}` | Cancel a **held** ticket |
| POST | `/orders/{id}/complete` | Take payment on a held ticket (Charge button) |
| POST | `/orders/{id}/void` | Void a **completed** sale (restocks items) |
| GET | `/customers?search=` | Search customers |
| POST | `/customers` | Create a customer |
| GET | `/customers/{id}` | Customer + recent order history |
| GET | `/register-sessions/current?business_type=retail` | This cashier's open shift, if any |
| POST | `/register-sessions/open` | Open a shift with a starting cash float |
| POST | `/register-sessions/{id}/close` | Close a shift, reconcile counted cash |

**Admin only:**

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/products` | Create a product |
| PATCH | `/products/{id}` | Edit a product |
| POST | `/products/{id}/adjust-stock` | Manual stock correction (+/-, with a reason) |
| GET / POST | `/suppliers` | List / create suppliers |
| GET / POST | `/purchases` | List / create purchase orders |
| POST | `/purchases/{id}/receive` | Mark received — adds stock, updates cost price |
| GET / POST | `/expenses` | List / log expenses |
| GET | `/expense-categories` | List expense categories |
| GET | `/reports/summary?date_from=&date_to=&business_type=` | Revenue, tax, discounts, expenses, net |
| GET | `/reports/top-products?date_from=&date_to=` | Best sellers by quantity |
| GET | `/reports/low-stock?threshold=10` | Products running low |

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

## Frontend integration

`frontend/index.html` already calls this API directly — categories and
products are fetched live (`GET /categories`, `GET /products`), Hold
creates/updates a real order (`POST /orders`, `PATCH /orders/{id}`), and
Charge finalizes one (`POST /orders` with `status: "completed"`, or
`POST /orders/{id}/complete` for a resumed ticket). The frontend reads
its target URL from `window.HABB_CONFIG.API_BASE_URL`, generated by
`frontend/build.js` from the `API_BASE_URL` Vercel env var — see the top-
level README for how the two projects connect.

---

## Deploying to a PHP host (cPanel) — matches HABB Stay's setup

This backend follows the same split as HABB Stay: the frontend is a
static site on Vercel, and the Laravel API + MySQL database live together
on a normal PHP host (MilesWeb/cPanel), reachable at
**`https://api.habbgate.com`**. That gives you a real persistent
filesystem, cron, queues, and a normal MySQL database — none of which a
serverless platform gives you for free.

Since HABB Stay's API already sits at the root of `api.habbgate.com`,
this POS app should go in **its own subdirectory** on the same server so
the two don't collide — e.g. `api.habbgate.com/pos`. In cPanel this is
usually done one of two ways; ask whoever manages the hosting which the
account supports:

- **"Setup Node.js/PHP App"** (if available) — create a second
  application on the account, set its document root to something like
  `~/pos-backend/public`, and map it to the `/pos` path or a dedicated
  subdomain.
- **Plain subdirectory** — upload this Laravel app to
  `~/api.habbgate.com/pos/`, point an `.htaccess` rewrite or an Apache
  alias at `pos/public`, so requests to `api.habbgate.com/pos/...` reach
  Laravel's front controller.

### Steps

1. **Database** — in cPanel, create a new MySQL database (e.g.
   `ikpguujl_habbpos`, following the same naming pattern as HABB Stay's
   `ikpguujl_habbstay`) and a database user with full privileges on it.
2. **Upload the code** — via cPanel's Git Version Control (point it at
   your `habb-pos` repo, `backend/` subfolder) or plain FTP/SFTP.
3. **Server-side setup** (via cPanel Terminal or SSH):
   ```bash
   composer install --no-dev --optimize-autoloader
   cp .env.example .env
   php artisan key:generate
   ```
4. **`.env`** — set:
   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://api.habbgate.com/pos

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=ikpguujl_habbpos
   DB_USERNAME=<your cPanel db user>
   DB_PASSWORD=<your cPanel db password>
   ```
5. **Migrate + seed**:
   ```bash
   php artisan migrate --seed --force
   ```
6. **Point the domain/subdirectory's document root at `public/`** — this
   is the one step that has to be done in cPanel's UI (or by whoever has
   access to it), since it's account-specific.
7. **CORS** — in `config/cors.php`, set `allowed_origins` to your Vercel
   frontend URL (e.g. `https://habb-pos.vercel.app`) once you have it,
   rather than `*`, since this is now a real production API.

From here, redeploying just means `git pull` + `composer install` on the
server (or re-running the cPanel Git deploy), and `php artisan migrate
--force` whenever the schema changes.

