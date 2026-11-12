# Project Status — restaurant-api

> What this backend is, what's been decided, where it stands, and what's next. Keep it short and current; update it at the end of each feature.

**Last updated:** 2026-11-12 · **Plan:** [BACKEND_PLAN.md](BACKEND_PLAN.md) (phases 0–12) · **Done:** Phase 0 foundation (PR #2) · **Done:** Phase 1 database (PR #3) · **Done:** Phase 3 auth (PR #4) · **Done:** Phase 4 menu: 4a uploads (PR #5), 4b categories (PR #6), 4c products (PR #7), 4d public `GET /menu` (PR #8) · **Contract amended** with Phases 1, 3 and 4 (frontend PR #39, merged) · **Done:** Phase 2 seed data (PR #10) · **Done:** Phase 5, the demo server on Hostinger (PRs #11, #12; contract amended in frontend PRs #42, #43) · **Milestone M1 reached (2026-11-12):** the frontend's login and whole menu area run on the demo server (her D7a–D7c, frontend PRs #44–#46), live on her Vercel showcase · 183 tests passing · **Next:** Phase 6 offers

## 1. What this is

The Laravel REST API for **Ahl Al Karam (أهل الكرم)**, a shawarma restaurant website + digital menu. Built to be reused for other restaurants (one restaurant per deployment, not multi-tenant).

- **Frontend** (separate repo, owner `rashahatoum`): Next.js 16 on Vercel, at `../restaurant-template` on this machine. Public menu, cart, WhatsApp ordering, and an owner dashboard. It started on mock data with the dashboard saving in the browser; **this API replaces both, one area at a time**. Since her D7 (2026-11-12), login and the whole menu area (public menu, categories, products, best sellers, their photos) use this API when `API_BASE_URL` is set; offers, banners, reviews, hero, about, site info and ordering are still mock data + the browser draft.
- **This repo** (`Awfa12/restaurant-api`): the database, the content, the one admin login, image uploads, and validation with Arabic messages.
- **Out of scope:** orders are **not stored** (they go from the customer's phone straight to WhatsApp), and there are no payments, customer accounts, analytics, email/SMS, or multiple restaurants.

## 2. The frontend repo: what to read there

The frontend repo is a **read-only reference** from this repo's sessions (`.claude/settings.json` allows reading it and blocks editing it). Run `git pull` in `../restaurant-template` before a session so the contract is current.

| Need | File in `../restaurant-template/` |
|---|---|
| **The agreed API contract** (endpoints, field limits, 422 keys, errors, hosting): **source of truth** | `.local-docs/API-CONTRACT.md` |
| The product, entities, cross-record rules, stable ids, seed data, public visibility rules | `.local-docs/BACKEND_REQUIREMENTS.md` |
| Why the contract is the way it is | `.local-docs/DECISIONS.md` (entries 2026-11-11: the agreement, "Contract amendments from the backend build" and "… from the backend's demo server"; 2026-11-12: D7a–D7c, how she switched login and the menu) |
| How her side calls us (since D7) | `src/lib/api/client.ts` (`apiFetch`, the only caller), `src/services/menu.ts` (`GET /menu`, tag `menu`), `src/app/(dashboard)/dashboard/menu-actions.ts` (the admin writes) |
| Exact JSON shapes (our Resources must match) | `src/types/menu.ts`, `content.ts`, `order.ts`, `dashboard.ts` |
| Seed content (same ids in our seeders) | `src/data/mock/*.ts`; photos in `public/assets/` |
| Validation rules + the Arabic messages to port | `src/lib/dashboard/validation.ts` |
| What the public site reads (visibility rules today) | `src/services/*.ts` |
| Every dashboard write (one interface, ~30 methods) | `src/lib/dashboard/repository.ts` |
| How the frontend turns our errors into messages | `src/lib/api/errors.ts` |

## 3. Decisions (agreed 2026-11-11, frontend PR #38; amended with the backend build, frontend PR #39)

Full details in the contract; the short version:

- **Separate repos.** No shared code; the HTTP contract is the only link. A contract change = a PR on `API-CONTRACT.md` in the frontend repo, reviewed by the other side, **before or with** the backend code. (The Claude GitHub integration has no write access to the frontend repo: the user opens those PRs himself.)
- **Server to server.** Only the Next.js server calls this API (never the browser) → no CORS, no CSRF, no Sanctum SPA cookies.
- **Auth:** Sanctum personal access tokens, `Authorization: Bearer`, valid **7 days**; login limited to 5 **failed** tries/min per username + client IP (`429` + `Retry-After`; the browser IP via `X-Client-IP` + `X-Frontend-Key`). Logout goes through `POST /auth/logout`. One admin user, created with `admin:create`.
- **JSON:** **camelCase**, exactly the frontend types; **not wrapped** in `data`; empty optional fields **left out** (not `null`); ids always **strings**.
- **Public ids = slugs** for categories, products, offers, branches. An optional `slug` in the body (lowercase kebab-case, ≤ 80); empty on create → a neutral id (`item-3f9a1c2b`), empty on update → kept; never changed by itself (transliteration rejected, decision 4.1). Sizes, phones, banners and reviews: database ids as strings. Product + offer slugs are unique **across both tables** (shared `/menu/<slug>` URL space). Keep the branch ids `qanawat-road` and `al-mehwari` (printed in QR codes).
- **Lists inside a record are saved by id, never deleted and recreated:** product sizes (cart lines store `optionId`), phones (`ordering.delivery.phoneId`), branches (QR codes). Sent with id → update; `id: ""` → create; missing → delete; an id of another parent → `422`. One transaction.
- **`PUT` = whole record** (missing optional field cleared), **`PATCH` = partial** (missing = unchanged, `null` = removed). Creates answer `201`.
- **Ordering:** `POST /admin/{resource}/{id}/move { direction: "up" | "down" }` returns the whole list in order (categories, offers, banners, reviews, best sellers).
- **Errors:** Laravel defaults, messages **always Arabic** (locale fixed to `ar`). `409` = a business rule refused the change (always with an Arabic message); no edit-conflict detection. Image too large: `422` on `image` (> 5 MB) or bare `413` (over PHP's limit).
- **Images:** `public` disk on `api.<domain>/storage/…`, max 5 MB, WebP/PNG/JPG (checked from the content, never SVG); image fields accept only our upload URLs; uploads unused after 24 h deleted by a daily scheduled job.
- **Hosting:** Hostinger web (shared) plan, EU data centre, `api.<domain>` (domain not bought yet). Frontend sets its Vercel function region near it. **Today (2026-11-11):** a **demo server** on the user's Hostinger Premium account (Frankfurt, PHP 8.4, MySQL), set up like his Suwayda-Under-Siege project, only to show the product to the restaurant owner. If he buys, production gets **its own hosting** (Phase 12). Runbook: [DEPLOYMENT.md](DEPLOYMENT.md). Her Vercel **production** site is also only a showcase for the owner, and it runs on the demo server (`API_BASE_URL` and `FRONTEND_API_KEY` are set on Vercel Production; she has no preview environment): a demo outage or reset shows there, and her builds read `GET /menu`.
- **Delivery order:** auth → categories + products + uploads → offers → banners → reviews → hero + about → site info → ordering. Each area goes to **staging** first; then the frontend switches that area.

## 4. Current state of this repo

| Item | State |
|---|---|
| Laravel | 13.42 (`laravel/framework ^13.17`), PHP **`^8.4`** (local 8.4.3). 8.4 is required: the lock file (Symfony 8.1, Pest 5, PHPUnit 13) doesn't install on 8.3 |
| Created with | `laravel new`: no starter kit, Blade stack (default, unused: API only), Pest 5 |
| Laravel Boost | 2.2: guidelines (`CLAUDE.md`), skills (`.claude/skills/`: `infer-conventions`, `laravel-best-practices`, `testing-best-practices`), MCP server (`.mcp.json`), Claude Code only. `tailwindcss-development` removed (also from `boost.json`). **Gotcha:** Boost treats the first occurrence of its opening tag in `CLAUDE.md` as the start of its block and replaces everything after it; never write that tag in the project section |
| API + Sanctum | ✅ Sanctum 4.4 (`install:api`), Bearer only (`sanctum.guard = []`), tokens 7 days. Routes in `routes/api.php` under **`/api/v1`** (`apiPrefix` in `bootstrap/app.php`). Guests get a JSON `401` (no redirect to a `login` route); errors under `api/*` are always JSON, even without `Accept: application/json`; Resources aren't wrapped in `data` |
| Database | **MySQL** locally (`restaurant_api`) and for tests (`restaurant_api_test`, `RefreshDatabase` on every feature test), decision 0.1. Collation `utf8mb4_unicode_ci` (also exists on MariaDB). Demo server: **MariaDB 11.8** (hPanel calls it "MySQL"), with `DB_CONNECTION=mysql` (decision 5.4); production: decided with its hosting |
| Locale | **`ar`** (default in `config/app.php` and `.env.example`). Fallback messages in `lang/ar/validation.php` (only the rules we use, no `:attribute`) and `lang/ar.json` (the "and N more errors" suffix), decision 0.2 |
| Web leftovers | Removed (decision 0.3): welcome page, `routes/web.php`, Vite/Tailwind/npm files. `composer dev` runs `php artisan serve` only (no Node, no queue worker) |
| CI | `.github/workflows/tests.yml`: Pint + `composer audit` + Pest on PHP 8.4 against MySQL 8.0, on every PR and push to `main` and `staging` |
| Deploy | ✅ Phase 5: `bin/deploy.sh` (manual, over SSH, guarded; caches via `composer cache:build`), `.env.staging.example`, the Suwayda layout (repo in `laravel/`, `public_html/` = shim + symlinks). Branch `staging` = what the demo runs. **The demo is up and seeded** (temporary Hostinger subdomain, PHP 8.4.27, MariaDB 11.8), the checks with the key passed, and the frontend uses it. Left for the user: the cron in hPanel (§3.7). A deploy never touches the data; a reset is optional (§5). [DEPLOYMENT.md](DEPLOYMENT.md) |
| Gate and rate limit | ✅ `API_REQUIRE_FRONTEND_KEY` (demo on): every `/api/v1` request needs `X-Frontend-Key`, else `403` (`RequireFrontendKey`, `App\Support\FrontendKey`). `throttle:public`: 300 / minute per client on `GET /menu`; every new public route goes in that group (BACKEND_PLAN cross-cutting rules) |
| Schema | ✅ Phase 1: [DATABASE.md](DATABASE.md) implemented (11 tables + `users`, `personal_access_tokens`, `cache`). Models in `app/Models`, enums in `app/Enums`, factories with states. No seed data yet (`DatabaseSeeder` is empty on purpose) |
| Auth | ✅ Phase 3: `POST /auth/login`, `POST /auth/logout`, `GET /auth/me`. Admin created with `php artisan admin:create <username>` (also the password reset). Failed logins limited per username + client IP; the client IP comes from the frontend's `X-Client-IP` only with the right `X-Frontend-Key` (`FRONTEND_API_KEY`) |
| Uploads | ✅ Phase 4a: `POST /admin/uploads` on the `public` disk (`storage/app/public/uploads`, served at `/storage/…`: run `php artisan storage:link` once per machine/server). Image fields accept only our upload URLs (`OwnUploadUrl`). Daily `uploads:prune` |
| Categories | ✅ Phase 4b: `/admin/categories` list, create, update (name, slug, photo), move, delete (`409` with products). Ordered lists use `App\Actions\OrderedList`; slugs `App\Support\Slugs` |
| Products | ✅ Phase 4c: `/admin/products` list, create, `PUT` full save (sizes synced by id), `PATCH` quick change, delete; `/admin/best-sellers/{id}/move`. `App\Actions\SaveProduct`, `App\Actions\BestSellers` |
| Public menu | ✅ Phase 4d: `GET /api/v1/menu` (no token), her `MenuSection[]`, 3 queries |
| Seed data | ✅ Phase 2: `database/seeders/data/` (8 JSON files + 21 photos, exported from her mock files with `composer seed:export`; needs Node 22 locally, never on the server). `php artisan migrate:fresh --seed` locally; refuses on a database that has content |
| Sessions / queue | `array` / `sync` (no `sessions` or queue tables; the daily jobs run from cron: token prune and uploads prune) |
| Tests | Admin tests sign in with `actingAsAdmin()` (`tests/Pest.php`, a Sanctum token): Laravel's `actingAs()` uses the session guard, which Sanctum doesn't check here. `APP_DEBUG=false` in `phpunit.xml`, so errors have the production shape (`{ message }`, no trace) |
| Git | PR #1 (docs), PR #2 (Phase 0), PR #3 (Phase 1), PR #4 (Phase 3), PR #5 (Phase 4a), PR #6 (Phase 4b), PR #7 (Phase 4c), PR #8 (Phase 4d), PR #9 (docs), PR #10 (Phase 2), PR #11 (Phase 5), PR #12 (deploy caches fix) merged; branch `staging` = the demo server; remote `github.com/Awfa12/restaurant-api` |

## 5. Next steps

The full sequence is in [BACKEND_PLAN.md](BACKEND_PLAN.md):

1. ~~Phase 0 foundation~~ (PR #2) · ~~Phase 1 database~~ (PR #3) · ~~Phase 3 auth~~ (PR #4) · ~~Phase 4a uploads~~ (PR #5) · ~~Phase 4b categories~~ (PR #6) · ~~Phase 4c products~~ (PR #7) · ~~Phase 4d public menu~~ (PR #8): **Phase 4 done**. ~~Contract PR~~ (frontend PR #39, merged: §5.1).
2. ~~Phase 2 seed data~~ (PR #10): we export the mock data ourselves (`composer seed:export`); `migrate:fresh --seed` reproduces her content and slugs.
3. ~~Phase 5, the demo server~~ (PRs #11, #12; contract: frontend PRs #42, #43): up, seeded, checked with the key. ~~Milestone M1~~ (2026-11-12): her D7a login (#44), D7b public menu (#45), D7c dashboard menu area (#46), live on the Vercel showcase. Left for the user: the cron in hPanel ([DEPLOYMENT.md](DEPLOYMENT.md) §3.7); optionally remove `DASHBOARD_USERNAME` / `DASHBOARD_PASSWORD` from Vercel (unused now that login goes through Laravel).
4. **Phase 6: offers, next** (reuses `OrderedList`, `Slugs`, `OwnUploadUrl`, the `PUT`/`PATCH` convention, and goes in `throttle:public`). The seed data already has them. Her side switches it the way D7c did the menu: offers' photos then upload to us instead of IndexedDB.

### 5.2 Contract note from Phase 5: done

Merged as **frontend PRs #42 and #43** (2026-11-11): `X-Frontend-Key` on every request (`403` «ما عندك صلاحية» without it, `/up` excepted), the public rate limit (300 / minute per client, `429` «محاولات كتير، جرّب بعد دقيقة» with `Retry-After`), the seed export answered, and the demo server + region `fra1`. Her D7b also updated API-CONTRACT §6: the Vercel production site is the showcase and uses the demo server.

### 5.1 Contract changes from Phases 1, 3 and 4: done

Merged as **frontend PR #39** (2026-11-11). Every item that used to be listed here (login headers and failed-tries limit, logout, slugs with neutral ids, `PUT`/`PATCH`, `201`, category `PATCH`, own-upload image URLs, foreign size ids, `bestSellerOrder`, the size-price rule, `""` for gone references, every branch in `GET /admin/ordering`) is now in `API-CONTRACT.md`, with the reasons in DECISIONS ("Contract amendments from the backend build") and the frontend tasks in API-CONTRACT §7.

Found while checking her code (in that PR): her `ProductForm` gives new sizes a made-up id (`size-xxxxxxxx`) instead of `id: ""`, so our `422` on foreign size ids would refuse a new size. It's on her §7 list; keep the API rule as it is.

## 6. Open questions

1. ~~**Frontend:** when will the mock data be exported as JSON for the seeders?~~ **Answered 2026-11-11: we export it ourselves** (`composer seed:export`, Phase 2). API-CONTRACT §9 updated in frontend PR #42.
2. **Both:** which domain? (decides `api.<domain>` and the QR codes). Not needed for the demo, which uses a temporary Hostinger subdomain; decided with the production hosting once the owner buys.
3. ~~**Hostinger plan**~~ **Answered 2026-11-11** for the demo: Premium (shared), SSH yes, PHP 8.4, Frankfurt, MySQL. `SELECT VERSION()` = **11.8.9-MariaDB** (hPanel labels it "MySQL"), used with the `mysql` driver; local is MySQL 9.2 and CI MySQL 8.0, so avoid features that differ between the engines (decision 5.4).
4. **Laravel's built-in English messages:** `401` "Unauthenticated." (as the contract shows), `404` "Not Found", `429` "Too Many Attempts.". Kept as is: the frontend shows its own Arabic text per status. Translate them only if the frontend asks.
5. **Claude Code guard:** the deny rules in `.claude/settings.json` block the Read/Edit tools on the frontend repo (tested), but not shell commands. Add `Bash(...)` deny patterns or the sandbox if needed.
6. **From BACKEND_REQUIREMENTS §11:** add the frontend owner as a collaborator on this repo; create the issue labels `backend`, `frontend`, `contract-change`.

## 7. Reference

- [GOOD_PRACTICES.md](GOOD_PRACTICES.md): the good practices adopted, declined and still to do (updated with every change that adds one).
- [DATABASE.md](DATABASE.md): tables, keys, delete rules, dashboard coverage.
- [LARAVEL_API_RECAP.md](LARAVEL_API_RECAP.md): Sanctum tokens, API Resources, FormRequests, where logic goes, Hostinger shared-hosting notes.
- Earlier discussion notes (outside both repos, historical): `../backend-notes/` (the backend's response to the draft contract, in English and Arabic, and the PR #38 and PR #39 descriptions).
