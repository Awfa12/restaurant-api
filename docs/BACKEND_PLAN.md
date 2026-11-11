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
PR #3 (branch `feat/phase-1-database`).
- ✅ [DATABASE.md](DATABASE.md) approved 2026-11-11 (decisions 1.1–1.8, §6), checked against every dashboard repository method (DATABASE.md §5).
- ✅ Migrations: 11 new tables; Laravel's `users` reworked (username, no email), `password_reset_tokens`, `sessions` and the queue tables removed (edited in place: nothing deployed). Sessions default to `array`, the queue to `sync` (config and `.env.example`).
- ✅ Models with `#[Fillable]`, casts (JSON lists, `ImageStyle` / `BannerTargetKind` enums in `app/Enums`), relations; `User` has `HasApiTokens`.
- ✅ Factories with states (best seller, banner targets, branch phones, delivery). `DatabaseSeeder` emptied: the default test user with password `password` must never reach staging or production; Phase 2 writes the real seeder.
- ✅ Tests: `tests/Feature/Database/SchemaRulesTest.php` (delete rules, unique slugs, casts). `migrate:fresh` runs on MySQL.

**Done when:** `DATABASE.md` is approved, and `php artisan migrate:fresh` runs on MySQL.

### Phase 2 — Seed data `DONE` · size S
PR #10 (branch `feat/phase-2-seed`). **We export the mock data ourselves** (the frontend won't): open question 1 answered.
- ✅ **Export** (dev-only, plain Node, no npm: decision 0.3 stays): `composer seed:export` = `node --experimental-strip-types database/seeders/data/export.mjs [../restaurant-template]`. Imports `src/data/mock/*.ts` read-only and writes 8 JSON files (her exact shapes) + the 21 photos they use (`database/seeders/data/images/`, 1.8 MB) + `source.json` (the frontend commit). Deterministic (a re-run with no mock change gives no diff). **Refuses** before writing when a mock file imports anything but types, an export is missing, a photo is missing, or a branch staff phone isn't one of the restaurant's public numbers (real staff numbers are never committed). Re-run after mock changes, review the diff, commit.
- ✅ **Seeders** (`DatabaseSeeder` → `MenuSeeder`, `OffersSeeder`, `BannersSeeder`, `ReviewsSeeder`, `HomeContentSeeder`, `RestaurantSeeder`, one transaction): slugs kept (categories, products, offers, branches: `qanawat-road`, `al-mehwari`); sizes, phones, banners, reviews get database ids; `delivery.phoneId`, `phone.branchId`, `dineIn.branchId` and the banner targets remapped. The mock's shared size group becomes 3 rows per product. Best sellers (no order in the mock) numbered 1…4 in her `sortBestSellers` order. The 5 settings rows + the ordering row.
- ✅ **Photos** (`Support\SeedImages`): copied to the public disk as `uploads/seed-<content hash>.webp` and registered in `uploads` (one upload per file, even when an offer and a banner share it), so `OwnUploadUrl` accepts them and `uploads:prune` keeps them. Deleted again if the seeding fails.
- ✅ **Refuses on a database with content** (any row in a content table), before writing anything: it can never mix with or overwrite the owner's edits. Local `migrate:fresh --seed` works; production: `db:seed --force` once, on the empty database (Phase 5).
- ✅ **No admin user in the seeder** (decision 2.1): `php artisan admin:create` on each server.
- ✅ Tests (`tests/Feature/Database/SeedDataTest.php`): `GET /menu` matches the JSON the way her `getMenu()` builds it (slugs, names, prices, sizes, order, best sellers); every remapped reference; settings rows; photos on disk, in `uploads`, accepted by `OwnUploadUrl`, kept by `uploads:prune`; a second run and a single non-empty table both refuse. Checked by breaking the guard, the delivery remap, the best-seller numbering and the upload registration once each.
- Private data check: the JSON has only the restaurant's two public numbers (each repeated by the placeholder staff phones), first names in reviews (her "realistic placeholders"), and no photo carries EXIF/XMP metadata.

**Done when:** `migrate:fresh --seed` reproduces the mock content and slugs. ✅

### Phase 3 — Auth `DONE` · size S
PR #4 (branch `feat/phase-3-auth`). Decisions 3.1–3.4 (2026-11-11), all as recommended, 3.3 with Option A.
- ✅ `POST /auth/login` → `{ token, user: { name } }`. Wrong credentials: `422` with `اسم المستخدم أو كلمة السر غلط` on `username` and as `message` (the frontend's text). Usernames normalized like the dashboard (NFC, trimmed, spaces collapsed), case-insensitive. A failed login always takes ≥ 400 ms (`Timebox`), so an unknown username and a wrong password look the same.
- ✅ **3.2** Rate limit on **failed** tries only: 5 / minute per username + client IP → `429` + `Retry-After` (`محاولات كتير، جرّب بعد دقيقة`); a success clears the count.
- ✅ **3.3 Option A:** the client IP is the browser's when the Next.js server sends `X-Client-IP` with the right `X-Frontend-Key` (`FRONTEND_API_KEY`, compared with `hash_equals`, must be a valid IP); otherwise the request IP. Works without the headers (then the dashboard shares Vercel's IP).
- ✅ **3.4** Tokens: `expires_at` = `sanctum.expiration` (7 days, also the config backstop); `sanctum.guard` = `[]` (Bearer only). Daily `sanctum:prune-expired --hours=24` (`routes/console.php`).
- ✅ `POST /auth/logout` deletes only the current device's token (`204`); `GET /auth/me` → `{ name }`, `401` when expired or deleted.
- ✅ **3.1 / decision 2.1:** `php artisan admin:create <username> [--name=]`: hidden password prompt (≥ 12 characters, confirmed, never a CLI option). For an existing username it sets the new password and logs out all its devices (the API has no password reset).
- ✅ Tests: `tests/Feature/Api/V1/AuthControllerTest.php`, `tests/Feature/Console/CreateAdminCommandTest.php`, `tests/Feature/Console/ScheduleTest.php`. The timebox, key check and counter reset were each checked by breaking the code once.

**Frontend can switch:** dashboard login (`services/auth.ts`).

### Phase 4 — Menu: categories, products, uploads `DONE` · size L (split into 4a–4d)
The biggest phase; the public site's main data. Decisions 4.1–4.4 agreed 2026-11-11 (§6). Order changed from the first plan: uploads first, because categories and products validate their images against them. One branch + PR per step.
- **4a Uploads `DONE`** (PR #5): `POST /admin/uploads` (multipart `image`) → `201 { url, width, height }`. WebP / PNG / JPG **detected from the content**, ≤ 5 MB, ≤ 4000 px per side, **no SVG** (it can carry scripts), random file names, Arabic messages. `OwnUploadUrl` rule (decision 4.3): an image field must be the URL of one of our uploads; `PublicImage` converts URL ↔ path. `php artisan uploads:prune` (daily): unused uploads older than 24 h; every image field is listed in `App\Support\ImageReferences`.
- **4b Categories `DONE`** (PR #6): `GET /admin/categories`, `POST` (`201`, last in order), `PATCH /{slug}` (`name?`, `slug?`, `image?`: only what's sent changes, an empty slug keeps the current one, `image: null` removes the photo), `POST /{slug}/move`, `DELETE /{slug}` (`204`, renumbered; `409` while it has products: `ما فيك تحذف قسم فيه أصناف (N). انقلها لقسم تاني أو احذفها أول.`). Names trimmed with inner spaces collapsed, unique. Optional `slug`, otherwise `cat-xxxxxxxx` (decision 4.1, `App\Support\Slugs`). Shared `App\Actions\OrderedList` (next position, move, renumber; reused by offers, banners, reviews and best sellers).
- **4c Products `DONE`** (PR #7): `GET /admin/products` (all, creation order), `POST` (`201`), **`PUT /{slug}` full save** (a missing optional field is cleared: `image`, `imageStyle`, `optionGroups`), **`PATCH /{slug}` quick change** (`price`, `isAvailable`, `isBestSeller`, `bestSellerOrder`; other fields ignored) (decision 4.2), `DELETE /{slug}` (`204`: sizes cascade, banner targets null, best sellers renumbered), `POST /admin/best-sellers/{slug}/move` (`409` `هالصنف مش بالأكثر مبيعاً. حدّث الصفحة.` for a product that isn't one). Sizes synced by id in `App\Actions\SaveProduct`; a size id of **another product** → `422` (and the update is also scoped to the product). Best sellers (`App\Actions\BestSellers`): on → last, or the fractional place sent by undo (1.5 → 2); off → renumbered; needs a photo (form text vs quick text); the full save keeps the place. Slugs unique across products and offers, otherwise `item-xxxxxxxx`. **Added beyond the frontend:** a quick price change that would make a size cost ≤ 0 is refused (`422` on `price`).
- **4d Public `GET /menu` `DONE`** (PR #8): no token; `[{ category, products }]` (her `MenuSection[]`): categories in order, empty ones left out, unavailable products included, products in creation order with their sizes. **Three queries whatever the menu's size** (tested with `expectsDatabaseQueryCount`): sizes eager-loaded, each product's category set by `chaperone()` without a query. Still to add: the contract test against the frontend's exported fixture (Phase 2).
- Validation: `checkProductInput`, `checkProductQuickChanges`, `validateCategoryName`, `validatePrice`.

**Frontend can switch:** the public menu, best sellers, cart catalog, product pages; dashboard menu, categories, products, best sellers, photos.

### Phase 5 — Demo server (staging) on Hostinger `IN PROGRESS` · size M
PR #11 (branch `feat/phase-5-staging`), fix PR (branch `fix/deploy-caches`). Decisions 5.1–5.6 (2026-11-11, §6). **Purpose:** show the product to the restaurant owner. Seed data only; if he buys, production gets its own hosting (Phase 12). Hostinger Premium (shared), PHP 8.4, MySQL, Frankfurt. The method is the user's Suwayda-Under-Siege setup, adapted to an API.
- ✅ **5.1 Layout:** the repo cloned over SSH (read-only deploy key of its own) into `~/domains/<demo>/laravel/`, next to `public_html/`, which holds only an `index.php` shim and two symlinks (`.htaccess`, `storage`). The code and `.env` are never reachable from the web.
- ✅ **5.3 `bin/deploy.sh`** (manual, over SSH): refuses `main`, a missing `.env`, `APP_ENV` local/testing/empty, `APP_DEBUG=true`, an empty `APP_KEY`, and the gate without a key; then fetch + `reset --hard`, `composer install --no-dev`, `migrate --force`, `composer cache:build` (config, routes, events: not `optimize`, which needs Blade views), `storage:link`, `chmod`. Wrapped in `main()` so the reset can't change the script mid-run. Branch flow `main` → `staging` (`--ff-only`).
- ✅ **5.2** `APP_ENV=staging` (strict mode on, `migrate:fresh --seed` allowed to reset the demo), `APP_DEBUG=false`, daily logs kept 14 days: `.env.staging.example`.
- ✅ **5.5** `throttle:public`: 300 requests / minute per client (the browser's IP when the frontend vouches for it) on `GET /menu` and later public routes; `429` with the contract's Arabic text and `Retry-After`.
- ✅ **5.6** `API_REQUIRE_FRONTEND_KEY`: with it on, every `/api/v1` request needs `X-Frontend-Key` (`403` «ما عندك صلاحية» otherwise; `/up` stays open; fails closed). The shared key check lives in `App\Support\FrontendKey` (also used by the login IP).
- ✅ `public/.htaccess` denies `.md .sh .yml .lock .sql .log .env .ini …` over HTTP. CI also runs on pushes to `staging`.
- ✅ Tests: `tests/Feature/Http/Middleware/RequireFrontendKeyTest.php`, the `throttle:public` group in `tests/Feature/Providers/AppServiceProviderTest.php`. Both checked by removing the wiring once.
- ✅ Runbook: [DEPLOYMENT.md](DEPLOYMENT.md) (hPanel, SSH, deploy key, shim, `.env`, first deploy, cron, smoke tests, demo reset, rollback, known Hostinger problems).
- ✅ On the server (2026-11-11): temporary subdomain with HTTPS, PHP 8.4.27 (web and SSH), MariaDB 11.8 database with its own user, deploy key, clone, shim, `.env`, first deploy (stopped at `optimize`'s view step: fixed in `bin/deploy.sh`, the rest finished by hand), `db:seed --force`, `admin:create`. From outside: `/up` 200; API without the key 403 (twice, not cached by Hostinger's CDN); `.env`, logs and folder listings refused.
- ⬜ The user: cron, the Postman checks with the key (menu, login, `/auth/me`, one photo), the `.sql` deny test; after the fix PR, fast-forward `staging` and re-run `bash bin/deploy.sh`.
- ⬜ Sent to the frontend **privately**: the URL, `FRONTEND_API_KEY` (sent on **every** request to the demo), a test admin account; she adds the image host to `images.remotePatterns` and sets Vercel's region to `fra1`. Contract note on the key and the public rate limit: PROJECT_STATUS §5.2.

**Done when:** the frontend's preview deployment logs in and shows the menu from the demo server.

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
- Only once the owner buys. **Its own hosting account** (the demo shares an account with another site, accepted for the demo only); [DEPLOYMENT.md](DEPLOYMENT.md) §9 lists what changes.
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
| 4.1 | Slug generation from Arabic | 4b | **Decided 2026-11-11: optional `slug` in the body, otherwise a neutral id** (`item-3f9a1c2b`, `cat-…`) · ~~`Str::slug`~~ (tested on the real names: `شاورما جاج` → `shaorma-gag`, `فروج` → `frog`) · ~~owner always types it~~ (blocks creating until her slug field exists) |
| 4.2 | Full save vs quick change | 4c | **Decided 2026-11-11: `PUT` = full save (missing optional fields cleared), `PATCH` = quick change.** Her full forms drop `undefined` keys, so a `PATCH` could never remove a photo or the sizes. Same convention later for offers, banners, reviews |
| 4.3 | Image fields | 4a | **Decided 2026-11-11: only URLs of our own uploads** (`OwnUploadUrl`) |
| 4.4 | Upload handling | 4a | **Decided 2026-11-11: checks only** (type by content, 5 MB, 4000 px, no SVG), no re-encoding (the dashboard already sends ≤ 800 px WebP) |
| 5.1 | Where the code lives on Hostinger | 5 | **Decided 2026-11-11: the Suwayda-Under-Siege layout:** repo in a sibling `laravel/` folder, `public_html/` = shim + symlinks · ~~document root on `public/`~~ (Hostinger may not allow it) · ~~repo inside `public_html` + deny rules~~ (one mistake exposes `.env`) |
| 5.2 | Demo server environment | 5 | **Decided 2026-11-11: `APP_ENV=staging`, `APP_DEBUG=false`** (no Basic Auth can hide debug pages; strict mode stays on; the demo can be reset with `migrate:fresh --seed`) · ~~`APP_ENV=production`~~ |
| 5.3 | How to deploy | 5 | **Decided 2026-11-11: manual `bash bin/deploy.sh` over SSH, from a read-only deploy key** (like Suwayda: every step visible, guarded) · ~~hPanel Git integration / webhook auto-deploy~~ (fails silently, no guard rails) |
| 5.4 | CI database engine | 5 | **Decided 2026-11-11: stays MySQL 8.0.** The demo turned out to be **MariaDB 11.8** (hPanel labels it "MySQL"), used with `DB_CONNECTION=mysql` like Suwayda: our schema needs nothing engine-specific (3 `json` columns, no UUID columns, no JSON queries). A MariaDB CI job is optional: revisit before a phase adds JSON queries or UUIDs, and for the production host |
| 5.5 | Public rate limit | 5 | **Decided 2026-11-11: 300 / minute per client** (`throttle:public`) |
| 5.6 | Keeping the demo private | 5 | **Decided 2026-11-11: `API_REQUIRE_FRONTEND_KEY`:** every `/api/v1` request needs `X-Frontend-Key` · ~~Basic Auth~~ (same `Authorization` header as the Bearer tokens) · ~~no gate~~ |
| 7.1 | Banner `href` in the public response | 7 | Backend resolves the link · frontend builds it from the target |

A decision that changes the API's behaviour also goes into `API-CONTRACT.md` (PR to the frontend repo).

## 7. Out of scope

Orders and order history, payments, customer accounts, analytics, email/SMS/WhatsApp sending, multiple restaurants or multi-tenancy, roles and permissions (one admin), Redis, queue workers, Docker. Revisit only with a real need.
