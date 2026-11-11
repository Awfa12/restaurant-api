# Project Status — restaurant-api

> What this backend is, what's been decided, where it stands, and what's next. Keep it short and current; update it at the end of each feature.

**Last updated:** 2026-11-11 · **Plan:** [BACKEND_PLAN.md](BACKEND_PLAN.md) (phases 0–12) · **Next:** Phase 0 foundation, then Phase 1 database design (discussion first, no code until approved)

## 1. What this is

The Laravel REST API for **Ahl Al Karam (أهل الكرم)**, a shawarma restaurant website + digital menu. Built to be reused for other restaurants (one restaurant per deployment, not multi-tenant).

- **Frontend** (separate repo, owner `rashahatoum`): Next.js 16 on Vercel, at `../restaurant-template` on this machine. Public menu, cart, WhatsApp ordering, and an owner dashboard. Today it runs on mock data and the dashboard saves in the browser; **this API replaces both**.
- **This repo** (`Awfa12/restaurant-api`): the database, the content, the one admin login, image uploads, and validation with Arabic messages.
- **Out of scope:** orders are **not stored** (they go from the customer's phone straight to WhatsApp), and there are no payments, customer accounts, analytics, email/SMS, or multiple restaurants.

## 2. The frontend repo: what to read there

The frontend repo is a **read-only reference** from this repo's sessions (`.claude/settings.json` allows reading it and blocks editing it). Run `git pull` in `../restaurant-template` before a session so the contract is current.

| Need | File in `../restaurant-template/` |
|---|---|
| **The agreed API contract** (endpoints, field limits, 422 keys, errors, hosting): **source of truth** | `.local-docs/API-CONTRACT.md` |
| The product, entities, cross-record rules, stable ids, seed data, public visibility rules | `.local-docs/BACKEND_REQUIREMENTS.md` |
| Why the contract is the way it is | `.local-docs/DECISIONS.md` (entry 2026-11-11) |
| Exact JSON shapes (our Resources must match) | `src/types/menu.ts`, `content.ts`, `order.ts`, `dashboard.ts` |
| Seed content (same ids in our seeders) | `src/data/mock/*.ts`; photos in `public/assets/` |
| Validation rules + the Arabic messages to port | `src/lib/dashboard/validation.ts` |
| What the public site reads (visibility rules today) | `src/services/*.ts` |
| Every dashboard write (one interface, ~30 methods) | `src/lib/dashboard/repository.ts` |
| How the frontend turns our errors into messages | `src/lib/api/errors.ts` |

## 3. Decisions (agreed 2026-11-11, frontend PR #38)

Full details in the contract; the short version:

- **Separate repos.** No shared code; the HTTP contract is the only link. A contract change = a PR on `API-CONTRACT.md` in the frontend repo, reviewed by the other side, **before or with** the backend code. (The Claude GitHub integration has no write access to the frontend repo: the user opens those PRs himself.)
- **Server to server.** Only the Next.js server calls this API (never the browser) → no CORS, no CSRF, no Sanctum SPA cookies.
- **Auth:** Sanctum personal access tokens, `Authorization: Bearer`, valid **7 days**; login limited to 5 tries/min per username + IP (`429` + `Retry-After`). One admin user.
- **JSON:** **camelCase**, exactly the frontend types; **not wrapped** in `data`; empty optional fields **left out** (not `null`); ids always **strings**.
- **Public ids = slugs** for categories, products, offers, branches. Slugs are suggested from the Arabic name, editable by the owner, never changed by themselves. Product + offer slugs are unique **across both tables** (shared `/menu/<slug>` URL space). Keep the branch ids `qanawat-road` and `al-mehwari` (printed in QR codes).
- **Lists inside a record are saved by id, never deleted and recreated:** product sizes (cart lines store `optionId`), phones (`ordering.delivery.phoneId`), branches (QR codes). Sent with id → update; `id: ""` → create; missing → delete. One transaction.
- **Ordering:** `POST /admin/{resource}/{id}/move { direction: "up" | "down" }` returns the whole list in order (categories, offers, banners, reviews, best sellers).
- **Errors:** Laravel defaults, messages **always Arabic** (locale fixed to `ar`). `409` = a business rule refused the change (always with an Arabic message); no edit-conflict detection. Image too large: `422` on `image` (> 5 MB) or bare `413` (over PHP's limit).
- **Images:** `public` disk on `api.<domain>/storage/…`, max 5 MB, WebP/PNG/JPG; uploads unused after 24 h deleted by a daily scheduled job.
- **Hosting:** Hostinger web (shared) plan, EU data centre, `api.<domain>` (domain not bought yet). Frontend sets its Vercel function region near it.
- **Delivery order:** auth → categories + products + uploads → offers → banners → reviews → hero + about → site info → ordering. Each area goes to **staging** first; then the frontend switches that area.

## 4. Current state of this repo

| Item | State |
|---|---|
| Laravel | 13.42 (`laravel/framework ^13.17`), PHP `^8.3` (local 8.4.3) |
| Created with | `laravel new`: no starter kit, Blade stack (default, unused: API only), Pest 5 |
| Laravel Boost | 2.2: guidelines (`CLAUDE.md`), skills (`.claude/skills/`), MCP server (`.mcp.json`), Claude Code only. The `tailwindcss-development` skill isn't needed here (no UI) |
| API + Sanctum | ❌ `php artisan install:api` **not run yet** (no `routes/api.php`, no `config/sanctum.php`) |
| Database | SQLite locally (`DB_CONNECTION=sqlite`); production is **MySQL** on Hostinger. Decide before the first migration |
| Locale | `APP_LOCALE=en` → to become `ar` (+ `lang/ar`) |
| Git | `first commit` only; remote `github.com/Awfa12/restaurant-api` |

## 5. Next steps

The full sequence is in [BACKEND_PLAN.md](BACKEND_PLAN.md); the first steps:

1. **Database schema design** (discussion, then approval):
   - Which entities get real tables (referenced by id: categories, products, option groups/options, offers, banners, reviews, branches, phones, dine-in settings) and which can be a JSON settings table (always saved whole: hero, about, offers/reviews section texts, ordering messages).
   - Slugs: columns, uniqueness across products + offers.
   - `order` columns and the move action; best-seller order.
   - Where each cross-record rule lives (BACKEND_REQUIREMENTS §4).
2. `php artisan install:api`; local database choice (MySQL recommended, to match Hostinger); `APP_LOCALE=ar`.
3. Seeders from the frontend mock data with the same ids (waiting for the frontend's JSON export).
4. First slice: auth (login / logout / me, rate limit) + its Pest tests.
5. Staging on Hostinger as soon as auth + `/menu` work.

## 6. Open questions

1. **Frontend:** when will the mock data be exported as JSON for the seeders?
2. **Both:** which domain? (decides `api.<domain>` and the QR codes)
3. **Hostinger plan:** SSH access (needed for composer + artisan)? PHP 8.3+ available? Which EU data centre?

## 7. Reference

- [LARAVEL_API_RECAP.md](LARAVEL_API_RECAP.md): Sanctum tokens, API Resources, FormRequests, where logic goes, Hostinger shared-hosting notes.
- Earlier discussion notes (outside both repos, historical): `../backend-notes/` (the backend's response to the draft contract, in English and Arabic, and the PR #38 description).
