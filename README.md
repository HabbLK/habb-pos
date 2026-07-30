# HABB POS

An original, HABB-branded point-of-sale system — a browser frontend and a
Laravel API backend, built from scratch for HABB Global.

## Structure

```
habb-pos/
├── frontend/
│   └── habb-pos.html      Self-contained POS UI (HTML/CSS/JS, no build step)
└── backend/
    ├── app/                Models, controllers, resources
    ├── database/           Migrations + seeder
    ├── routes/api.php      REST API routes
    └── README.md           Full backend setup + API reference
```

## Frontend

Open `frontend/habb-pos.html` directly in a browser — no build step, no
dependencies beyond two Google Fonts loaded over CDN. It ships with a
hardcoded sample catalog (retail / café / service modes) so it works
standalone.

Design system: Paper / Ink / Navy / Orange / Teal palette, Lora + Inter
type, and the HABB circular receipt-terminal mark used throughout —
including a hand-drawn "sale complete" stamp animation.

## Backend

A Laravel API for persisting products, categories, held tickets, and
completed sales — see `backend/README.md` for full setup steps and the
API reference. Not wired into the frontend yet (the HTML currently runs on
its own hardcoded catalog); that's the natural next step once this is
pushed and the backend is running somewhere reachable.

## Status

- [x] Frontend prototype (in-browser cart, checkout, hold/resume)
- [x] Backend API skeleton (Laravel, framework-agnostic to DB engine)
- [ ] Frontend wired to backend via fetch
- [ ] Auth / multi-terminal support
- [ ] Receipt printing
