# Backend Plan — restaurant-api

> The phases for building the Laravel API, what each one delivers, and when the frontend can switch over.
> Source of truth for *what* the API does: `../restaurant-template/.local-docs/API-CONTRACT.md` (agreed 2026-11-11). This file is the *how* and *in what order*.
> Status of each phase: `TODO` · `IN PROGRESS` · `DONE` (only when it exists, works, and its tests pass). **Last updated:** 2026-11-12.

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
- **Every public route goes in the `throttle:public` group** in `routes/api.php` (300 / minute per client, decision 5.5). `tests/Feature/Http/PublicRoutesTest.php` enforces it for every route without a token. Admin routes stay behind `auth:sanctum`; login keeps its own limit.

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
- **4d Public `GET /menu` `DONE`** (PR #8): no token; `[{ category, products }]` (her `MenuSection[]`): categories in order, empty ones left out, unavailable products included, products in creation order with their sizes. **Three queries whatever the menu's size** (tested with `expectsDatabaseQueryCount`): sizes eager-loaded, each product's category set by `chaperone()` without a query. The contract test against her data came with Phase 2: `SeedDataTest` checks `GET /menu` against the exported mock JSON, built the way her `getMenu()` builds it.
- ✅ Validation ported (§4): `validateCategoryName` → `StoreCategoryRequest`, `UpdateCategoryRequest`; `checkProductInput` + `validatePrice` → `SaveProductRequest` (`POST` and `PUT`); `checkProductQuickChanges` → `PatchProductRequest`.

**Frontend can switch:** the public menu, best sellers, cart catalog, product pages; dashboard menu, categories, products, best sellers, photos. ✅ Switched in her D7a–D7c (frontend PRs #44–#46, 2026-11-12).

**Done when:** the menu endpoints match the contract, with tests. ✅

### Phase 5 — Demo server (staging) on Hostinger `DONE` · size M
PR #11 (branch `feat/phase-5-staging`), PR #12 (branch `fix/deploy-caches`). Contract amended in frontend PRs #42 and #43. Decisions 5.1–5.6 (2026-11-11, §6). **Purpose:** show the product to the restaurant owner. Seed data only; if he buys, production gets its own hosting (Phase 12). Hostinger Premium (shared), PHP 8.4, MySQL, Frankfurt. The method is the user's Suwayda-Under-Siege setup, adapted to an API.
- ✅ **5.1 Layout:** the repo cloned over SSH (read-only deploy key of its own) into `~/domains/<demo>/laravel/`, next to `public_html/`, which holds only an `index.php` shim and two symlinks (`.htaccess`, `storage`). The code and `.env` are never reachable from the web.
- ✅ **5.3 `bin/deploy.sh`** (manual, over SSH): refuses `main`, a missing `.env`, `APP_ENV` local/testing/empty, `APP_DEBUG=true`, an empty `APP_KEY`, and the gate without a key; then fetch + `reset --hard`, `composer install --no-dev`, `migrate --force`, `composer cache:build` (config, routes, events: not `optimize`, which needs Blade views), `storage:link`, `chmod`. Wrapped in `main()` so the reset can't change the script mid-run. Branch flow `main` → `staging` (`--ff-only`).
- ✅ **5.2** `APP_ENV=staging` (strict mode on, `migrate:fresh --seed` allowed to reset the demo), `APP_DEBUG=false`, daily logs kept 14 days: `.env.staging.example`.
- ✅ **5.5** `throttle:public`: 300 requests / minute per client (the browser's IP when the frontend vouches for it) on `GET /menu` and later public routes; `429` with the contract's Arabic text and `Retry-After`.
- ✅ **5.6** `API_REQUIRE_FRONTEND_KEY`: with it on, every `/api/v1` request needs `X-Frontend-Key` (`403` «ما عندك صلاحية» otherwise; `/up` stays open; fails closed). The shared key check lives in `App\Support\FrontendKey` (also used by the login IP).
- ✅ `public/.htaccess` denies `.md .sh .yml .lock .sql .log .env .ini …` over HTTP. CI also runs on pushes to `staging`.
- ✅ Tests: `tests/Feature/Http/Middleware/RequireFrontendKeyTest.php`, the `throttle:public` group in `tests/Feature/Providers/AppServiceProviderTest.php`. Both checked by removing the wiring once.
- ✅ Runbook: [DEPLOYMENT.md](DEPLOYMENT.md) (hPanel, SSH, deploy key, shim, `.env`, first deploy, cron, smoke tests, optional reset, rollback, known Hostinger problems).
- ✅ On the server (2026-11-11): temporary subdomain with HTTPS, PHP 8.4.27 (web and SSH), MariaDB 11.8 database with its own user, deploy key, clone, shim, `.env`, first deploy (stopped at `optimize`'s view step: fixed in `bin/deploy.sh`, the rest finished by hand), `db:seed --force`, `admin:create`. From outside: `/up` 200; API without the key 403 (twice, not cached by Hostinger's CDN); `.env`, logs and folder listings refused.
- ✅ After PR #12: `staging` fast-forwarded and `bash bin/deploy.sh` ran clean. The checks with the key passed (menu, login, `/auth/me`, one photo), and a real `.sql` file is refused (`403`).
- ✅ Sent to the frontend **privately**: the URL, `FRONTEND_API_KEY` (sent on **every** request to the demo) and a test admin account. Contract note on the key and the public rate limit: frontend PRs #42 and #43.
- ✅ **Milestone M1 (2026-11-12):** her D7a (login, PR #44), D7b (public menu, PR #45) and D7c (dashboard menu area, PR #46) are merged. Her Vercel **production** site is the owner's showcase (she has no preview environment), so `API_BASE_URL` and `FRONTEND_API_KEY` are set there and it runs on the demo server. A cold build reads `GET /menu` 56 times with 7 real requests (the Data Cache shares one), far under the limit.
- ⬜ Follow-up for the user: the cron in hPanel (DEPLOYMENT.md §3.7). Until it runs, expired tokens and unused uploads aren't pruned; nothing breaks.

**Done when:** the frontend logs in and shows the menu from the demo server. ✅ (on the Vercel showcase)

### Phase 6 — Offers `DONE` · size M
PR #14 (branch `feat/phase-6-offers`), on staging 2026-11-17. Frontend: contract answers (her PR #50), `API_AREAS` (#51), offers live after the dashboard pass on the demo (#53). Decisions 6.1–6.3 (2026-11-17, §6), sent to the frontend as the D7 contract answers. No migration: the table and the `offers_section` settings row exist since Phase 1, and the seed already has the offers.
- ✅ **Public `GET /offers`** (in `throttle:public`): her `OffersContent` flat, `{ isEnabled, eyebrow, title, menuCardImage?, offers }`, only active offers, in order. A raw `null` body when the section is off **or** has no active offer (decision 6.3; `response()->json(null)` would send `{}`, which her reader refuses: tested).
- ✅ **Admin:** `GET /admin/offers` = the same flat shape with every offer (decision 6.2); `POST` (`201`, last, optional `slug` else `offer-xxxxxxxx`, unique against products too); **`PUT /{slug}` whole offer** (a missing `imageStyle` / `badge` is cleared; an empty slug keeps the current one); **`PATCH /{slug}` = `isActive` only** (decision 6.1); `DELETE` (`204`, renumbered); `POST /{slug}/move` → every offer (`OrderedList`). `PATCH /admin/offers-section`: partial, returns her `OffersSettings`, `menuCardImage: null` removes the photo (`App\Support\OffersSection` reads and writes the settings row; photos stored as paths).
- ✅ **Validation** (§4): `checkOfferInput` → `SaveOfferRequest` (empty item lines dropped like her form; the price rules and texts shared with products); `checkSectionText` → `UpdateOffersSectionRequest` + the new rule **`App\Rules\MarkedText`** (length without `**`, odd `**` refused; reused by reviews and About).
- ✅ **Guard for the cross-cutting rule:** `tests/Feature/Http/PublicRoutesTest.php` fails when any route without a token (login excepted) is outside `throttle:public`. Checked by moving `/offers` out of the group once.
- ✅ Tests: `OffersControllerTest`, `Admin/OfferControllerTest`, `Admin/OffersSectionControllerTest`, `Rules/MarkedTextTest`, `PublicRoutesTest`, and `SeedDataTest` now checks that the seeded offers come out of `GET /offers` exactly as her mock (photos as our uploads). 244 tests.

**Frontend can switch:** offers. ✅ Switched (`API_AREAS=menu,offers` on Vercel Production).

**Done when:** the offers endpoints match the contract answers, with tests, and run on staging. ✅

### Phase 7 — Menu banners `DONE` · size M
PR #15 (branch `feat/phase-7-banners`), on staging 2026-11-17. Frontend: banners live after the dashboard pass on the demo (her PR #54). Decisions 7.1–7.4 (2026-11-17, §6). No migration: the table, its target keys (null on delete) and the seed exist since Phases 1 and 2.
- ✅ **Public `GET /banners`** (in `throttle:public`): her `MenuBanner[]`, active slides in order, `action.target` as stored (decision 7.1). A button that leads nowhere is **left out, the slide stays** (decision 7.3): a deleted category or product, a category with no products (not on the public menu), offers hidden (`OffersSection::isShown()`, the same rule as `GET /offers`' `null`). An unavailable product keeps its button (the menu still shows it). **5 queries whatever the number of slides** (tested with `expectsDatabaseQueryCount`).
- ✅ **Admin:** `GET /admin/banners` = every slide, a deleted target sent with an empty id (`productId: ""`) so the dashboard shows "target gone"; `POST` (`201`, last); **`PUT /{id}` whole slide** (a missing eyebrow or button is cleared); **`PATCH /{id}` = `isActive` only**; `DELETE` (`204`, renumbered); `POST /{id}/move` → every slide. Ids are the database id as a string.
- ✅ **Validation** (§4): `checkBannerInput` → `SaveBannerRequest`, errors on the contract's keys (`image`, not `image.src`; `action.label`, `action.target`). Title line breaks normalized to `\n` (two lines × 22). A gone target (`""`) is refused like an unknown one. `image.alt` ≤ 120, falls back to the title on one line (decision 7.2). One new text beyond hers: «اختار وين بياخد الزر» for an unknown target kind (her form can't send one).
- ✅ **Shared pieces:** `PatchIsActiveRequest` (was `PatchOfferRequest`, now offers and banners), `OwnUploadUrl::accepts()` (the image check reported on another key), `OffersSection::isShown()`.
- ✅ Tests: `BannersControllerTest`, `Admin/BannerControllerTest`, `SeedDataTest` (seeded banners through `GET /banners` = her mock). **Found and fixed:** `OwnUploadUrlTest`'s "refuses" dataset passed a closure instead of a URL (Pest passes a `Closure`-typed dataset value uncalled), so it passed whatever the rule did; now it fails when the rule is broken (checked). 287 tests.

**Frontend can switch:** banners. ✅ Switched (`API_AREAS=menu,offers,banners` on Vercel Production).

**Done when:** the banner endpoints match the contract, with tests, and run on staging. ✅

### Phase 8 — Reviews `DONE` · size S
PR #16 (branch `feat/phase-8-reviews`), on staging 2026-11-17. Frontend: reviews live after the dashboard pass on the demo (her PR #55). Same shapes as offers (decisions 6.1–6.3, sent in the D7 contract answers). No migration: the table, the `reviews_section` row and the 4 seeded reviews exist since Phases 1 and 2.
- ✅ **Public `GET /reviews`** (in `throttle:public`): her `ReviewsContent` flat, `{ isEnabled, eyebrow, title, defaultQuestion, reviews }`, visible reviews only, in order; a raw `null` when the section is off **or** has no visible review (as her `visibleReviewsOnly`).
- ✅ **Admin:** `GET /admin/reviews` = the same flat shape with every review; `POST` (`201`, last); **`PUT /{id}` whole review** (a missing `question` / `time` is cleared); **`PATCH /{id}` = `isVisible` only** (`PatchIsVisibleRequest`); `DELETE` (`204`, renumbered); `POST /{id}/move` → every review. `PATCH /admin/reviews-section`: partial, returns her `ReviewsSettings`. Ids are database ids as strings; an empty `question` is left out (the site shows `defaultQuestion`).
- ✅ **Validation** (§4): `checkReviewInput` → `SaveReviewRequest` (the answer through `MarkedText`: ≤ 220 without `**`, several marked parts, odd `**` refused); `checkReviewsSettings` → `UpdateReviewsSectionRequest` (eyebrow and title from `UpdateOffersSectionRequest::sectionTextRules()`, the rules both sections share).
- ✅ **Shared settings rows:** `App\Support\SectionSettings` (abstract: key, defaults, PATCH fields, `applyChanges()` hook), extended by `OffersSection` (its photo) and `ReviewsSection`. The offers tests guard the refactor.
- ✅ **Lengths:** her JS `.length` counts an emoji as 2, PHP `mb_strlen` as 1, so her limit is the stricter one: what her dashboard accepts we accept (no surprise `422`); kept as is (decision 8.1).
- ✅ Tests: `ReviewsControllerTest`, `Admin/ReviewControllerTest`, `Admin/ReviewsSectionControllerTest`, `SeedDataTest` (seeded reviews through `GET /reviews` = her mock). The "no visible review" `null` checked by breaking the rule once. 322 tests.

**Frontend can switch:** reviews. ✅ Switched (`API_AREAS=menu,offers,banners,reviews` on Vercel Production).

**Done when:** the review endpoints match the contract, with tests, and run on staging. ✅

### Phase 9 — Hero + About `DONE` · size S
PR #17 (branch `feat/phase-9-home`), on staging 2026-11-17. Frontend: hero and About live after the dashboard pass on the demo (her PR #56). Decisions 9.1–9.3 (2026-11-17, §6). No migration: the `hero` and `about` settings rows are seeded since Phase 2.
- ✅ **Public `GET /hero`, `GET /about`** (in `throttle:public`): her `HeroContent` / `AboutContent` exactly (an empty `badge` left out, the About photo as a URL). Nothing is hidden, so the admin GETs return the same.
- ✅ **Admin:** `GET` + **`PUT /admin/hero`** and **`PUT /admin/about`**: the whole record (a missing `badge` is cleared), stored through `SectionSettings::replace()` (`App\Support\HeroContent`, `AboutContent`).
- ✅ **Validation** (§4): `checkHeroInput` → `UpdateHeroRequest` (exactly 3 features; each `features.<i>.label` ≤ 28 and `.icon` one of the new `App\Enums\FeatureIcon`); `checkAboutInput` → `UpdateAboutRequest` (eyebrow ≤ **40** and title through the shared section-text rules with About's limits and `partial: false`; 1–4 paragraphs, each ≤ 320 without `**` with its error on `paragraphs.<i>`, an empty one refused, not dropped; closing ≤ 120 with marks; the photo required and ours).
- ✅ **Alt text** (decision 9.1): the sent `image.alt`, or the title without its markers (what her form sends for a new photo).
- ✅ **Shared pieces:** `sectionTextRules($limits, $partial)` (2 options, `isEnabled` moved to the two section requests); the trait `Concerns\ChecksContentImage` (a `{ src, alt }` photo, error on `image`) now used by banners and About.
- ✅ Tests: `HomeContentTest` (public), `Admin/HeroControllerTest`, `Admin/AboutControllerTest`, `SeedDataTest` (seeded hero and About through the public GETs = her mock). The "whole save needs every field" rule was first tested with `eyebrow: null`, which also passes with the partial rules (a present key still runs `required`); the test now leaves the key out, and fails when the rule is broken (checked). 363 tests.

**Frontend can switch:** hero + About. ✅ Switched (`API_AREAS=…,home` on Vercel Production).

**Done when:** the hero and About endpoints match the contract, with tests, and run on staging. ✅

### Phase 10 — Site info `IN PROGRESS` · size M
Branch `feat/phase-10-site-info`: code and tests done; left: the PR, then Phase 11, then **both** on staging before the frontend turns `info` and `ordering` on together (her `API_AREAS` refuses one without the other). Decisions 10.1–10.3 (2026-11-17, §6). No migration.
- ✅ **Public `GET /site-info`** (in `throttle:public`): her `SiteInfo` with **the branch staff phones left out** (tested: their numbers never appear in the body). Phones and branches in their saved order, branch ids = slugs. **3 queries** (tested).
- ✅ **Admin:** `GET /admin/site-info` = everything, a staff phone with its `branchId` (slug); **`PUT /admin/site-info`** = the whole record in one transaction (`App\Actions\SaveSiteInfo`):
  - **saved by id** (§4.1): sent with an id → updated, `id: ""` → created, missing → deleted; order = the order sent (it decides the branch phone that gets table orders);
  - a new branch takes its `slug` (`branches.<i>.slug`) or `branch-xxxxxxxx`; **a saved branch's slug never changes**, even if one is sent (printed in the QR codes);
  - a deleted branch takes its staff phones (cascade) and table settings (branch columns) with it; a sent phone of a deleted branch is dropped (decision 10.2);
  - **phones are synced before branches:** a staff phone moved to the restaurant in the same save that deletes its old branch would otherwise go with the cascade (tested; the test fails with the order swapped);
  - **`409`** (her `orderingConflicts`): delivery on and its phone removed or without WhatsApp; a branch with tables on left without a WhatsApp phone of its own (named in the message). Nothing is written on a `409` (tested).
- ✅ **Validation** (§4): `checkSiteInfoInput` → `UpdateSiteInfoRequest` (tagline ≤ 120 and ≤ 3 lines; ≥ 1 restaurant phone, ≤ 10; numbers `+963` + 8–9 digits; no number twice per owner; 1–6 branches, name ≤ 30, address ≤ 80, `mapUrl` https; hours text and schema together; social links normalized as her `normalizeSocialUrl`, ported to `App\Support\SocialLinks`). Ids not ours → `422` (decision 10.1); server-only guards: hours text ≤ 80, one link per platform (decision 10.3).
- ✅ Tests: `SiteInfoControllerTest` (public), `Admin/SiteInfoControllerTest` (round trip GET → PUT unchanged; every save-by-id case; the `409`s; 25 validation cases), `Unit/Support/SocialLinksTest`, `SeedDataTest` (seeded info through the public GET = her mock without the staff phones). 419 tests.

**Done when:** the site-info endpoints match the contract, with tests, and run on staging (with Phase 11).

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
| M1: staging + login + menu ✅ 2026-11-12 | 5 | Build `apiFetch`, switch login and the whole menu area (her D7a–D7c, PRs #44–#46) |
| M2: content | 6–9 | Switch offers, banners, reviews, hero, about |
| M3: restaurant settings | 10–11 | Switch site info and ordering; remove mock data and the browser draft |
| M4: launch | 12 | Go live on the domain, print QR codes |

## 4. Validation to port (frontend `src/lib/dashboard/validation.ts`)

| Frontend function | Phase | Laravel |
|---|---|---|
| `validateCategoryName` | 4b | `StoreCategoryRequest`, `UpdateCategoryRequest` |
| `checkProductInput`, `validatePrice`, `parsePriceInput` limits | 4c | `SaveProductRequest` (`POST` and `PUT`) |
| `checkProductQuickChanges` | 4c | `PatchProductRequest` |
| `checkOfferInput` | 6 ✅ | `SaveOfferRequest` (`POST` and `PUT`) |
| `checkSectionText` | 6 ✅, 8 ✅ | `UpdateOffersSectionRequest::sectionTextRules()` + custom rule `MarkedText` (length without `**`, odd `**` refused) |
| `checkBannerInput` | 7 ✅ | `SaveBannerRequest` (`POST` and `PUT`) |
| `checkReviewInput`, `checkReviewsSettings` | 8 ✅ | `SaveReviewRequest` (`POST` and `PUT`), `UpdateReviewsSectionRequest` |
| `checkHeroInput`, `checkAboutInput` | 9 ✅ | `UpdateHeroRequest`, `UpdateAboutRequest` |
| `checkSiteInfoInput` | 10 ✅ | `UpdateSiteInfoRequest` + `SaveSiteInfo` action (409 rules) |
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
| 6.1 | Full save vs on/off for offers, banners, reviews | 6 | **Decided 2026-11-17: `PUT` = whole record, `PATCH` = `isActive` / `isVisible` only** (the contract's §2 rule; its §4 table listed only `PATCH`) |
| 6.2 | Shape of `GET /admin/offers` and `/admin/reviews` | 6 | **Decided 2026-11-17: her `OffersContent` / `ReviewsContent`, flat** (section settings + every item; what her dashboard seed reads) · ~~a list + a separate `GET /admin/offers-section`~~ (one more request and endpoint, no gain) · ~~`{ section, offers }`~~ (not her type) |
| 6.3 | Public `null` | 6 | **Decided 2026-11-17: `null` when the section is off or has no active (visible) item**, as her mock-mode `getOffersContent` / `getReviewsContent` |
| 7.1 | Banner `href` in the public response | 7 | **Decided 2026-11-17: the stored `action.target`; the frontend builds the link** (routes are hers); the API leaves out a button whose target is gone · ~~backend resolves the link~~ |
| 7.2 | Banner photo alt text | 7 | **Decided 2026-11-17: accept `image.alt` (≤ 120); missing → the title on one line** (what her form sends), so a photo never goes without one |
| 7.3 | When is a banner target "gone" on the public site | 7 | **Decided 2026-11-17: not reachable, not only deleted:** a deleted or empty category, a deleted product, offers hidden. Her `bannerHref` checks the same on her side |
| 7.4 | Public banners query cost | 7 | **Decided 2026-11-17: a fixed number of queries** (targets eager-loaded, categories with their product count), with a query-count test like `/menu` |
| 8.1 | Text lengths with emoji | 8 | **Decided 2026-11-17: keep PHP's count** (`mb_strlen`: an emoji is 1; her `.length`: 2). Her limit is the stricter one, so nothing her dashboard accepts is refused here · ~~count UTF-16 units like JS~~ (extra code, no visible gain) |
| 9.1 | About photo alt text | 9 | **Decided 2026-11-17: the sent `image.alt`; missing → the title without its markers** (what her form sends for a new photo; same idea as 7.2) |
| 9.2 | Section-text rules for About | 9 | **Decided 2026-11-17: one `sectionTextRules($limits, $partial)`** for offers, reviews and About (About: eyebrow 40, and `required` for a whole save, not `sometimes`) · ~~a copy in the About request~~ |
| 9.3 | Where the hero and About rows live | 9 | **Decided 2026-11-17: the `SectionSettings` base**, with a new `replace()` for a whole save · ~~a second settings helper~~ |
| 10.1 | Phone or branch ids that aren't ours in a site-info save | 10 | **Decided 2026-11-17: `422`**, like sizes: `phones.<i>.id` «هالرقم مش موجود، حدّث الصفحة», `branches.<i>.id` «هالفرع مش موجود، حدّث الصفحة» (new texts; her form can't send them) |
| 10.2 | A phone pointing at a branch | 10 | **Decided 2026-11-17:** a branch deleted in the same save → the phone is dropped (as her code); an unknown branch → `422` on `phones.<i>.branchId` |
| 10.3 | Guards her form never hits | 10 | **Decided 2026-11-17: hours text ≤ 80, one social link per platform** («في رابط تاني لنفس المنصة»), so a direct call can't store junk |
| 11.2 | A branch left out of `PUT /admin/ordering` | 11 | **Decided 2026-11-17: switched off, its table count kept** (her form always sends every branch) |
| 11.3 | The delivery phone while delivery is off | 11 | **Decided 2026-11-17: kept if it's a restaurant phone, else stored empty** (`phoneId: ""` in the GET), as her code only checks it while delivery is on |
| 11.1 | Public `GET /ordering` keys | 11 | **Decided 2026-11-17: her `OrderingOptions`** without `restaurantName` / `timeZone`: `{ delivery: { fee, whatsApp } \| null, dineInBranches: [{ id, name, tableCount, whatsApp }], message, thankYou }` |

A decision that changes the API's behaviour also goes into `API-CONTRACT.md` (PR to the frontend repo).

## 7. Out of scope

Orders and order history, payments, customer accounts, analytics, email/SMS/WhatsApp sending, multiple restaurants or multi-tenancy, roles and permissions (one admin), Redis, queue workers, Docker. Revisit only with a real need.
