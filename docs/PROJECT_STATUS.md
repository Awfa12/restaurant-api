# Project Status — restaurant-api

> What this backend is, what's been decided, where it stands, and what's next. Keep it short and current; update it at the end of each feature.

**Last updated:** 2026-11-11 · **Plan:** [BACKEND_PLAN.md](BACKEND_PLAN.md) (phases 0–12) · **Done:** Phase 0 foundation (PR #2) · **Done:** Phase 1 database (PR #3) · **Done:** Phase 3 auth (PR #4) · **Now:** Phase 4 menu: 4a uploads done (branch `feat/phase-4a-uploads`, 75 tests passing), next 4b categories

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
| Laravel | 13.42 (`laravel/framework ^13.17`), PHP **`^8.4`** (local 8.4.3). 8.4 is required: the lock file (Symfony 8.1, Pest 5, PHPUnit 13) doesn't install on 8.3 |
| Created with | `laravel new`: no starter kit, Blade stack (default, unused: API only), Pest 5 |
| Laravel Boost | 2.2: guidelines (`CLAUDE.md`), skills (`.claude/skills/`: `infer-conventions`, `laravel-best-practices`, `testing-best-practices`), MCP server (`.mcp.json`), Claude Code only. `tailwindcss-development` removed (also from `boost.json`). **Gotcha:** Boost treats the first occurrence of its opening tag in `CLAUDE.md` as the start of its block and replaces everything after it; never write that tag in the project section |
| API + Sanctum | ✅ Sanctum 4.4 (`install:api`), Bearer only (`sanctum.guard = []`), tokens 7 days. Routes in `routes/api.php` under **`/api/v1`** (`apiPrefix` in `bootstrap/app.php`). Guests get a JSON `401` (no redirect to a `login` route); errors under `api/*` are always JSON, even without `Accept: application/json`; Resources aren't wrapped in `data` |
| Database | **MySQL** locally (`restaurant_api`) and for tests (`restaurant_api_test`, `RefreshDatabase` on every feature test), decision 0.1. Collation `utf8mb4_unicode_ci` (also exists on MariaDB). Production: MySQL on Hostinger (engine/version to confirm) |
| Locale | **`ar`** (default in `config/app.php` and `.env.example`). Fallback messages in `lang/ar/validation.php` (only the rules we use, no `:attribute`) and `lang/ar.json` (the "and N more errors" suffix), decision 0.2 |
| Web leftovers | Removed (decision 0.3): welcome page, `routes/web.php`, Vite/Tailwind/npm files. `composer dev` runs `php artisan serve` only (no Node, no queue worker) |
| CI | `.github/workflows/tests.yml`: Pint + Pest on PHP 8.4 against MySQL 8.0, on every PR and push to `main` |
| Schema | ✅ Phase 1: [DATABASE.md](DATABASE.md) implemented (11 tables + `users`, `personal_access_tokens`, `cache`). Models in `app/Models`, enums in `app/Enums`, factories with states. No seed data yet (`DatabaseSeeder` is empty on purpose) |
| Auth | ✅ Phase 3: `POST /auth/login`, `POST /auth/logout`, `GET /auth/me`. Admin created with `php artisan admin:create <username>` (also the password reset). Failed logins limited per username + client IP; the client IP comes from the frontend's `X-Client-IP` only with the right `X-Frontend-Key` (`FRONTEND_API_KEY`) |
| Uploads | ✅ Phase 4a: `POST /admin/uploads` on the `public` disk (`storage/app/public/uploads`, served at `/storage/…`: run `php artisan storage:link` once per machine/server). Image fields accept only our upload URLs (`OwnUploadUrl`). Daily `uploads:prune` |
| Sessions / queue | `array` / `sync` (no `sessions` or queue tables; the daily jobs run from cron: token prune and uploads prune) |
| Tests | Admin tests sign in with `actingAsAdmin()` (`tests/Pest.php`, a Sanctum token): Laravel's `actingAs()` uses the session guard, which Sanctum doesn't check here |
| Git | PR #1 (docs), PR #2 (Phase 0), PR #3 (Phase 1), PR #4 (Phase 3) merged; remote `github.com/Awfa12/restaurant-api` |

## 5. Next steps

The full sequence is in [BACKEND_PLAN.md](BACKEND_PLAN.md):

1. ~~Phase 0 foundation~~ (PR #2) · ~~Phase 1 database~~ (PR #3) · ~~Phase 3 auth~~ (PR #4) · ~~Phase 4a uploads~~ (branch `feat/phase-4a-uploads`).
2. **Contract PR** (the user opens it on `API-CONTRACT.md`): the items in §5.1. Best before her apiRepository work, since 4.1 and 4.2 change how she calls the menu endpoints.
3. **Phase 4b** categories → **4c** products → **4d** public `GET /menu`.
4. **Phase 2:** seeders from the frontend mock data with the same slugs (waiting for the frontend's JSON export, open question 1), the settings rows, and the seeded photos registered in `uploads`. No admin in the seeder.
5. Staging on Hostinger as soon as auth + `/menu` work; there, `php artisan admin:create` and the shared `FRONTEND_API_KEY` (sent to the frontend privately).

### 5.1 Contract changes to propose (one PR on `API-CONTRACT.md`)

From Phase 3 (auth):
- `POST /auth/login` accepts two optional headers from the Next.js server: `X-Client-IP` (the browser IP, from `x-forwarded-for`) and `X-Frontend-Key` (`FRONTEND_API_KEY`, a shared secret in server-only env vars). With the right key, the 5-failures limit counts per browser; without it, all dashboard logins share Vercel's IP.
- Wrong credentials: `422` with the generic message on `username` and as the top-level `message`.
- The dashboard's logout must call `POST /auth/logout` (deleting the cookie alone leaves the token valid until it expires).
- Changing the admin password (`admin:create` on the server) logs out every device.

From Phase 4 (menu, decisions 4.1–4.4):
- **Slugs (replaces "suggested from the Arabic name"):** create/update bodies of categories, products, offers and branches take an optional `slug` (lowercase Latin kebab-case, ≤ 80, unique; products and offers checked against each other). Empty → a neutral id (`item-3f9a1c2b`, `cat-…`). Automatic transliteration was tested and rejected (`فروج` → `frog`).
- **`PUT /admin/products/{id}` = full save** (a missing optional field such as `image`, `imageStyle` or `optionGroups` is cleared); **`PATCH` = quick change** (`price`, `isAvailable`, `isBestSeller`, `bestSellerOrder` only). Her `saveProduct` calls `PUT`, `updateProduct` calls `PATCH`. Same convention later for offers, banners and reviews.
- Creates answer `201`; `POST /admin/uploads` answers `201 { url, width, height }`.
- Image fields accept only URLs returned by `POST /admin/uploads` (`422`: `الصورة مش من الصور المرفوعة، ارفعها من جديد`).
- A size id that belongs to another product → `422` on `optionGroups.0.options.<i>.id`.

From Phase 1 (database, [DATABASE.md](DATABASE.md) §6):
- A banner target or delivery phone that no longer exists is sent by the admin GET with an empty id (`""`).
- Sizes, phones, banners and reviews get new numeric ids at seeding.
- `PATCH /admin/products/{id}` also takes `bestSellerOrder` (the undo of «شيل من الأكثر مبيعاً»).
- `GET /admin/ordering` returns `dineIn.branches` for every branch.

## 6. Open questions

1. **Frontend:** when will the mock data be exported as JSON for the seeders?
2. **Both:** which domain? (decides `api.<domain>` and the QR codes)
3. **Hostinger plan:** SSH access (needed for composer + artisan)? **PHP 8.4 available?** (required, see §4) Which EU data centre? **MySQL or MariaDB, which version?** (local is MySQL 9.2, CI is MySQL 8.0: avoid 9.x-only features until we know)
4. **Laravel's built-in English messages:** `401` "Unauthenticated." (as the contract shows), `404` "Not Found", `429` "Too Many Attempts.". Kept as is: the frontend shows its own Arabic text per status. Translate them only if the frontend asks.
5. **Claude Code guard:** the deny rules in `.claude/settings.json` block the Read/Edit tools on the frontend repo (tested), but not shell commands. Add `Bash(...)` deny patterns or the sandbox if needed.
6. **From BACKEND_REQUIREMENTS §11:** add the frontend owner as a collaborator on this repo; create the issue labels `backend`, `frontend`, `contract-change`.

## 7. Reference

- [GOOD_PRACTICES.md](GOOD_PRACTICES.md): the good practices adopted, declined and still to do (updated with every change that adds one).
- [DATABASE.md](DATABASE.md): tables, keys, delete rules, dashboard coverage.
- [LARAVEL_API_RECAP.md](LARAVEL_API_RECAP.md): Sanctum tokens, API Resources, FormRequests, where logic goes, Hostinger shared-hosting notes.
- Earlier discussion notes (outside both repos, historical): `../backend-notes/` (the backend's response to the draft contract, in English and Arabic, and the PR #38 description).
