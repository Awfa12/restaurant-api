# Backend Plan — restaurant-api

> The phases for building the Laravel API, what each one delivers, and when the frontend can switch over.
> Source of truth for *what* the API does: `../restaurant-template/.local-docs/API-CONTRACT.md` (agreed 2026-11-11). This file is the *how* and *in what order*.
> Status of each phase: `TODO` · `IN PROGRESS` · `DONE` (only when it exists, works, and its tests pass). **Last updated:** 2026-11-11.

## 1. What we're building

### Endpoints (from the contract)

| Group | Endpoints | Count |
|---|---|---|
| Public (no auth) | `GET /menu`, `/offers`, `/banners`, `/reviews`, `/hero`, `/about`, `/site-info`, `/ordering` | 8 |
| Auth | `POST /auth/login`, `POST /auth/logout`, `GET /auth/me` | 3 |
| Admin: menu | categories (list, create, update, delete, move), products (list, create, update/partial, delete), best sellers (move) | 10 |
| Admin: content | offers (5 + section), banners (5), reviews (5 + section), hero (get/put), about (get/put) | ~20 |
| Admin: restaurant | site info (get/put), ordering (get/put) | 4 |
| Admin: images | `POST /admin/uploads` | 1 |

All under `/api/v1`. Every admin endpoint is behind `auth:sanctum`.

### Data

| Kind | Entities |
|---|---|
| Lists (rows, ordered) | categories, products (+ option groups + options), offers, banner slides, reviews, branches, phones, dine-in settings per branch |
| Singletons (one record each) | offers section, reviews section, hero, about, site info texts, ordering settings, admin user |
| Not stored | orders, customers, payments |

### Cross-cutting rules (apply in every phase)

- **Shapes:** an API Resource per entity, camelCase, exactly the frontend type (`src/types/`). Responses not wrapped; empty optional fields left out; ids are strings.
- **Validation:** a FormRequest per write, rules and Arabic messages ported from the matching `check…` function in `src/lib/dashboard/validation.ts` (table in §4).
- **Business rules** that span records live in an Action class, inside a `DB::transaction()`, and return `409` with an Arabic message when they refuse.
- **Lists inside a record are saved by id** (sync): sizes, phones, branches.
- **Public vs admin:** public endpoints apply the visibility rules (BACKEND_REQUIREMENTS §7); admin endpoints return everything.

## 2. Phases

Each phase ends with Pest tests passing, Pint clean, `docs/PROJECT_STATUS.md` updated, and, for an area the frontend uses, a note to the frontend that it's ready on staging. One branch + PR per phase (or per sub-step for the big ones).

### Phase 0 — Foundation `DONE` · size S
Make the empty project an API project. Branch `chore/phase-0-foundation`.
- ✅ `php artisan install:api` (Sanctum 4.4, `routes/api.php`, the tokens migration); `apiPrefix: 'api/v1'` in `bootstrap/app.php`. The sample `/user` route was removed (not in the contract).
- ✅ **Database:** MySQL for development and tests (decision 0.1); `phpunit.xml` uses `restaurant_api_test`, `RefreshDatabase` on all feature tests.
- ✅ `ar` as the default locale (`config/app.php` + `.env.example`); `lang/ar/validation.php` for the rules we use and `lang/ar.json` for the "(and N more errors)" suffix (decision 0.2).
- ✅ Errors always JSON for `api/*` (`shouldRenderJsonWhen`, already in the first commit), and `redirectGuestsTo(fn () => null)`: Laravel 13 sends guests to `route('login')` by default, which doesn't exist here and would turn a missing token into a `500`.
- ✅ `JsonResource::withoutWrapping()` in `AppServiceProvider`.
- ✅ Health check: Laravel's built-in `/up`.
- ✅ GitHub Actions (`.github/workflows/tests.yml`): Pint + Pest, PHP 8.4, MySQL 8.0. **PHP 8.4 is the minimum** (`composer.json` `^8.4`): the lock file (Symfony 8.1, Pest 5, PHPUnit 13) doesn't install on 8.3, as the first CI run showed.
- ✅ Removed (decision 0.3): welcome page, `routes/web.php`, Vite/Tailwind/npm files, the `tailwindcss-development` skill (and from `boost.json`), npm steps in `composer setup`; `composer dev` = `php artisan serve`.
- ✅ Tests: `tests/Feature/Api/FoundationTest.php` (Arabic 422 without `Accept`, JSON 401 without/with a bad token, no `data` wrapper, JSON 404, no page at `/`, `/up`). The two Laravel example tests were deleted (approved).
- ✅ Full suite on the local MySQL test database (7 tests, 2026-11-11); `migrate` run on `restaurant_api`.

**Done when:** `GET /api/v1/…` routes exist, validation errors come back as Arabic JSON, and tests pass.

### Phase 1 — Database design `DONE` · size M
Branch `feat/phase-1-database`.
- ✅ [DATABASE.md](DATABASE.md) approved 2026-11-11 (decisions 1.1–1.8, §6), checked against every dashboard repository method (DATABASE.md §5).
- ✅ Migrations: 11 new tables; Laravel's `users` reworked (username, no email), `password_reset_tokens`, `sessions` and the queue tables removed (edited in place: nothing deployed). Sessions default to `array`, the queue to `sync` (config and `.env.example`).
- ✅ Models with `#[Fillable]`, casts (JSON lists, `ImageStyle` / `BannerTargetKind` enums in `app/Enums`), relations; `User` has `HasApiTokens`.
- ✅ Factories with states (best seller, banner targets, branch phones, delivery). `DatabaseSeeder` emptied: the default test user with password `password` must never reach staging or production; Phase 2 writes the real seeder.
- ✅ Tests: `tests/Feature/Database/SchemaRulesTest.php` (delete rules, unique slugs, casts). `migrate:fresh` runs on MySQL.

**Done when:** `DATABASE.md` is approved, and `php artisan migrate:fresh` runs on MySQL.

### Phase 2 — Seed data `TODO` · size S (blocked on the frontend's JSON export)
- Seeders from the frontend mock data **with the same ids** (`src/data/mock/`): about 15 categories + products, 4 offers, 3 banners, 4 reviews, 2 branches, and the singletons.
- Photos from `../restaurant-template/public/assets/` → `storage/app/public/…`; records store the new URLs.
- **Contract test (strong check):** the frontend exports what its services return today (e.g. `getMenu()` → `menu.json`). After seeding, `GET /api/v1/menu` must return **the same JSON**. One fixture per public endpoint, added in each area's phase.
- **No admin user in the seeder** (decision 2.1, decided in Phase 3): the admin is created on each server with `php artisan admin:create`.

**Done when:** `migrate:fresh --seed` reproduces the mock content and ids.

### Phase 3 — Auth `DONE` · size S
Branch `feat/phase-3-auth`. Decisions 3.1–3.4 (2026-11-11), all as recommended, 3.3 with Option A.
- ✅ `POST /auth/login` → `{ token, user: { name } }`. Wrong credentials: `422` with `اسم المستخدم أو كلمة السر غلط` on `username` and as `message` (the frontend's text). Usernames normalized like the dashboard (NFC, trimmed, spaces collapsed), case-insensitive. A failed login always takes ≥ 400 ms (`Timebox`), so an unknown username and a wrong password look the same.
- ✅ **3.2** Rate limit on **failed** tries only: 5 / minute per username + client IP → `429` + `Retry-After` (`محاولات كتير، جرّب بعد دقيقة`); a success clears the count.
- ✅ **3.3 Option A:** the client IP is the browser's when the Next.js server sends `X-Client-IP` with the right `X-Frontend-Key` (`FRONTEND_API_KEY`, compared with `hash_equals`, must be a valid IP); otherwise the request IP. Works without the headers (then the dashboard shares Vercel's IP).
- ✅ **3.4** Tokens: `expires_at` = `sanctum.expiration` (7 days, also the config backstop); `sanctum.guard` = `[]` (Bearer only). Daily `sanctum:prune-expired --hours=24` (`routes/console.php`).
- ✅ `POST /auth/logout` deletes only the current device's token (`204`); `GET /auth/me` → `{ name }`, `401` when expired or deleted.
- ✅ **3.1 / decision 2.1:** `php artisan admin:create <username> [--name=]`: hidden password prompt (≥ 12 characters, confirmed, never a CLI option). For an existing username it sets the new password and logs out all its devices (the API has no password reset).
- ✅ Tests: `tests/Feature/Api/V1/AuthControllerTest.php`, `tests/Feature/Console/CreateAdminCommandTest.php`, `tests/Feature/Console/ScheduleTest.php`. The timebox, key check and counter reset were each checked by breaking the code once.

**Frontend can switch:** dashboard login (`services/auth.ts`).

### Phase 4 — Menu: categories, products, uploads `TODO` · size L (split into 4a–4d)
The biggest phase; the public site's main data.
- **4a Public `GET /menu`:** categories in order with their products and sizes; empty categories left out; unavailable products included. Contract test against the frontend fixture.
- **4b Categories (admin):** list, create (last in order), rename, photo (`image: null` removes), move up/down, delete (`409` while it has products). Slug suggested from the Arabic name (test `Str::slug` on real names: decision 4.1).
- **4c Products (admin):** create, full save, partial `PATCH` (price, `isAvailable`, `isBestSeller`), delete. Sizes synced by id. Best sellers: turning on → last; off → renumber; needs a photo; move up/down. Product slugs unique across products and offers.
- **4d Uploads:** `POST /admin/uploads` (WebP/PNG/JPG, ≤ 5 MB, **no SVG**: it can carry scripts), random file names, returns `{ url, width, height }`. Daily job deletes uploads no record uses after 24 h.
- Validation: `checkProductInput`, `checkProductQuickChanges`, `validateCategoryName`, `validatePrice`.

**Frontend can switch:** the public menu, best sellers, cart catalog, product pages; dashboard menu, categories, products, best sellers, photos.

### Phase 5 — Staging on Hostinger `TODO` · size M
As soon as Phase 3 + 4 work, so the frontend can test against a real server.
- Check the plan: SSH, **PHP ≥ 8.4**, MySQL, EU data centre.
- Subdomain (`api-staging.<domain>`, or a temporary one) with its document root on Laravel's **`public/`**.
- MySQL database + user; `.env` (staging values, `APP_DEBUG=false`); `php artisan key:generate`, `migrate --force`, `db:seed`.
- `storage:link`; PHP `upload_max_filesize` / `post_max_size` ≥ 6 MB; HTTPS.
- Cron: `* * * * * php artisan schedule:run` (prune tokens, image cleanup).
- Deploy steps written in `docs/DEPLOY.md` (git pull, `composer install --no-dev -o`, migrate, `config:cache`, `route:cache`).
- Check the `Authorization` header reaches Laravel (Apache: Laravel's `public/.htaccess` handles it).
- Send the frontend, **privately**: the staging URL and an admin account. They add the image host to `next.config.ts`.

**Done when:** the frontend's preview deployment logs in and shows the menu from staging.

### Phase 6 — Offers `TODO` · size M
- Public `GET /offers` (section + active offers, `null` when the section is off).
- Admin: offers CRUD, on/off, move, delete; `PATCH /admin/offers-section` (partial). Offer slugs share the URL space with products.
- Validation: `checkOfferInput`, `checkSectionText` (lengths without `**`, odd `**` refused: a custom rule reused later).

### Phase 7 — Menu banners `TODO` · size M
- Public `GET /banners`: active slides in order; a button whose target is gone (offers off, category empty or deleted, product gone) is dropped, the slide stays.
- Admin: CRUD, on/off, move. Target stored as `{ kind, categoryId | productId }`, never a link.
- Validation: `checkBannerInput`.
- **Decision 7.1:** does the public response send the resolved `href` (the frontend's `MenuBannerSlideData`) or only the target, leaving the frontend to build the link? The contract allows both.

### Phase 8 — Reviews `TODO` · size S
- Public `GET /reviews` (section + visible reviews, `null` when off).
- Admin: CRUD, shown/hidden, move; `PATCH /admin/reviews-section`.
- Validation: `checkReviewInput`, `checkReviewsSettings`.

### Phase 9 — Hero + About `TODO` · size S
- Public `GET /hero`, `GET /about`; admin `GET/PUT` for each (whole record).
- Validation: `checkHeroInput` (exactly 3 features, known icons), `checkAboutInput` (1–4 paragraphs, highlights).

### Phase 10 — Site info `TODO` · size M
The trickiest rules.
- Public `GET /site-info`: **branch staff phones left out**.
- Admin `PUT /admin/site-info`: whole record; phones and branches synced by id (new = `id: ""`; new branches get a slug); deleting a branch deletes its staff phones and dine-in settings; `409` when delivery or a branch with tables would be left without a WhatsApp number.
- Validation: `checkSiteInfoInput` (phone format `+963…`, social link domains, hours text + schema together).

### Phase 11 — Ordering settings `TODO` · size M
- Public `GET /ordering`: only enabled types and branches, with the WhatsApp numbers resolved.
- Admin `GET/PUT /admin/ordering`: checked against the saved site info (what's on needs a WhatsApp number; tables on → at least one branch on; `tableCount` 0–99, and ≥ 1 when that branch is on: API-CONTRACT §4).
- Validation: `checkOrderingInput` (only `{restaurant}` / `{name}` placeholders).

**After Phase 11:** every area of the frontend can run on the API.

### Phase 12 — Production launch `TODO` · size M
- Domain bought → `api.<domain>`; production database and `.env`; real content seeded or entered.
- Backups: check Hostinger's backup frequency, and add a daily database dump if it isn't daily.
- Logs: `LOG_CHANNEL=daily` with a retention limit (shared hosting disk).
- Security pass: every admin route behind `auth:sanctum`, writes use `validated()` only (no mass assignment), uploads checked by content, rate limits, `APP_DEBUG=false`.
- Smoke test with the frontend on production; then the table QR codes can be printed.

## 3. Milestones with the frontend

| Milestone | After phase | The frontend can |
|---|---|---|
| M1: staging + login + menu | 5 | Build `apiFetch`, switch login and the whole menu area |
| M2: content | 6–9 | Switch offers, banners, reviews, hero, about |
| M3: restaurant settings | 10–11 | Switch site info and ordering; remove mock data and the browser draft |
| M4: launch | 12 | Go live on the domain, print QR codes |

## 4. Validation to port (frontend `src/lib/dashboard/validation.ts`)

| Frontend function | Phase | Laravel |
|---|---|---|
| `validateCategoryName` | 4b | `StoreCategoryRequest`, `UpdateCategoryRequest` |
| `checkProductInput`, `validatePrice`, `parsePriceInput` limits | 4c | `StoreProductRequest`, `UpdateProductRequest` |
| `checkProductQuickChanges` | 4c | `PatchProductRequest` |
| `checkOfferInput` | 6 | `StoreOfferRequest` / `UpdateOfferRequest` |
| `checkSectionText` | 6, 8 | Custom rule `MarkedText` (length without `**`, odd `**` refused) |
| `checkBannerInput` | 7 | `StoreBannerRequest` / `UpdateBannerRequest` |
| `checkReviewInput`, `checkReviewsSettings` | 8 | Review requests + section request |
| `checkHeroInput`, `checkAboutInput` | 9 | `UpdateHeroRequest`, `UpdateAboutRequest` |
| `checkSiteInfoInput` | 10 | `UpdateSiteInfoRequest` + `SaveSiteInfo` action (409 rules) |
| `checkOrderingInput` | 11 | `UpdateOrderingRequest` + `SaveOrdering` action |

The limits (`PRODUCT_LIMITS`, `OFFER_LIMITS`, …) are constants in that file; copy the numbers, keep the Arabic texts word for word, and use the field keys from API-CONTRACT §4.

## 5. Testing approach

- **Feature tests (Pest) per endpoint:** the happy path, each validation rule that matters, each `409` rule, auth required on admin routes.
- **Contract tests:** public responses compared to the frontend's exported fixtures (Phase 2), so a shape mistake is caught here, not on the live site.
- **No mocking the database:** tests run against a real test database (MySQL or SQLite in memory: decision 0.1).
- Don't aim for a coverage number: test the rules in BACKEND_REQUIREMENTS §4 and the contract's 422 keys.

## 6. Decisions to make (and when)

| # | Decision | Phase | Options |
|---|---|---|---|
| 0.1 | Local + test database | 0 | **Decided 2026-11-11: MySQL for both** (matches Hostinger) · ~~SQLite (simpler)~~ |
| 0.2 | Arabic validation texts | 0 | **Decided 2026-11-11: write `lang/ar` ourselves** for the rules we use (no dependency) · ~~`laravel-lang`~~ |
| 0.3 | Remove Vite/Tailwind/welcome page | 0 | **Decided 2026-11-11: remove** · ~~keep~~ |
| 1.1–1.8 | Schema choices | 1 | **Decided 2026-11-11, all as recommended:** hybrid singletons (JSON `settings` + `ordering_settings` row + dine-in on `branches`) · auto-increment id + unique slug, product/offer slugs checked across both tables by a rule · `position` columns, no unique index · `sizes_label` + `product_sizes` · banner targets as FKs, null on delete · images stored as paths + `uploads` table · restrict/cascade/null-on-delete as in DATABASE.md §4 · default Laravel tables trimmed. Details: [DATABASE.md](DATABASE.md) |
| 2.1 | Creating the admin account | 3 | **Decided 2026-11-11: `php artisan admin:create`** (hidden prompt, doubles as password reset) · ~~seeder from env vars~~ (plain-text password left in `.env`) |
| 4.1 | Slug generation from Arabic | 4b | `Str::slug` (test on real names) · owner always types it |
| 7.1 | Banner `href` in the public response | 7 | Backend resolves the link · frontend builds it from the target |

A decision that changes the API's behaviour also goes into `API-CONTRACT.md` (PR to the frontend repo).

## 7. Out of scope

Orders and order history, payments, customer accounts, analytics, email/SMS/WhatsApp sending, multiple restaurants or multi-tenancy, roles and permissions (one admin), Redis, queue workers, Docker. Revisit only with a real need.
