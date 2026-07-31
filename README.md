# HABB POS

An original, HABB-branded point-of-sale system, built the same way HABB
Stay is: a static frontend on Vercel talking to a Laravel API + MySQL
hosted together on a normal PHP server.

```
 GitHub repo (HabbLK/habb-pos)
        │
        ├──► Vercel (frontend/)            ──env var──►  Laravel API (backend/)
        │    static site, API_BASE_URL          │         on MilesWeb/cPanel
        │    baked in at build time              ▼         api.habbgate.com/pos
        │                                    MySQL database
        │                                    (ikpguujl_habbpos)
```

## Structure

```
habb-pos/
├── frontend/
│   ├── index.html       POS terminal (HTML/CSS/JS, no framework/bundler)
│   ├── admin.html        Back office: dashboard, inventory, purchases,
│   │                     customers, expenses (admin role only)
│   ├── shared.css         Design tokens + components shared by both pages
│   ├── config.js           Checked-in fallback; overwritten at build time
│   ├── build.js             Writes config.js from the API_BASE_URL env var
│   ├── package.json          Just enough for Vercel's build step
│   └── vercel.json            Vercel build/output config
└── backend/
    ├── app/               Models, controllers, resources, middleware
    ├── database/          Migrations + seeder
    ├── routes/api.php     REST API routes
    └── README.md          Full backend setup, API reference, cPanel deploy
```

## How it fits together

- **Frontend** is deployed to Vercel as a static site. There's no
  JS framework — `build.js` is the entire "build step", and its only
  job is to read the `API_BASE_URL` environment variable (set in the
  Vercel project settings) and write it into `config.js`, which
  `index.html` reads at runtime. This mirrors how HABB Stay's frontend
  gets its API URL baked in.
- **Backend** is a normal Laravel app — not serverless — deployed to the
  same MilesWeb/cPanel account as HABB Stay, in its own subdirectory
  (`api.habbgate.com/pos`) so the two don't collide. It has a real
  filesystem, MySQL, and can run cron/queues if needed later.
- All money math (subtotal, discount, tax, total, change due) is computed
  server-side from live product prices — the frontend's numbers are only
  ever a preview before the API confirms them.

See `backend/README.md` for the full setup, API reference, and cPanel
deployment steps.

## Status

- [x] Frontend UI (HABB design system: Paper/Ink/Navy/Orange/Teal, Lora +
      Inter, circular receipt-terminal mark, HABB logo)
- [x] Backend API (products, categories, held tickets, checkout)
- [x] Frontend wired to the backend via `fetch` (catalog, hold/resume,
      payment)
- [x] Vercel build config matching HABB Stay's env-var-at-build pattern
- [x] **Auth & roles** — Sanctum login, admin/cashier roles
- [x] **Cash register sessions** — open with a float, close with cash
      reconciliation
- [x] **Customers** — loyalty points, credit ("pay later") balance
- [x] **Suppliers & purchasing** — purchase orders, receiving stock
- [x] **Expenses** — categorized tracking
- [x] **Reports** — sales summary, top products, low stock (`admin.html`
      dashboard)
- [x] **Refunds/voids** — full-order void with automatic restock
- [x] **Receipt printing** — print-friendly view after checkout
- [ ] Deployed and reachable at api.habbgate.com/pos (pending server setup)
- [ ] Partial/line-level returns (currently full-order void only)
- [ ] Multi-location / multi-register-per-shift support

Demo logins (seeded, change before production): `admin@habb.lk` /
`cashier@habb.lk`, password `password`. Admin-only pages/actions
(Inventory, Purchases, Expenses, Reports — all in `frontend/admin.html`)
require the `admin` role; the POS terminal (`frontend/index.html`) works
for either role.
