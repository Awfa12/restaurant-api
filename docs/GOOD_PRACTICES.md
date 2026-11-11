# Good Practices — restaurant-api

> Every good-practice decision we take, why, and where it lives. **Add a row whenever we adopt one, or decide not to** (with the reason), so the next person, or the next session, knows it was deliberate. Newest at the bottom of each section.

## Adopted

### Security

| Practice | Why | Where | Since |
|---|---|---|---|
| No secrets in the repo: credentials, tokens, private phone numbers, `.env` values | A public or leaked repo must not hand out access | `.gitignore` (`.env`), CLAUDE.md rule; staging/admin credentials sent privately | 2026-11-11 |
| Admin routes protected by Sanctum Bearer tokens, valid 7 days; Laravel stores only a hash | The API is public (no IP allow-list possible from Vercel); a database leak doesn't leak working tokens | `auth:sanctum`, Phase 3 | 2026-11-11 |
| Mass assignment only through `#[Fillable]` lists; writes use `validated()` only | A request can't set columns it shouldn't (e.g. `best_seller_order`, `slug` of another record) | `app/Models/*` | 2026-11-11 |
| **No hard-coded seed user** (the default `DatabaseSeeder` created one with password `password`) | `db:seed` runs on staging and production: that would be a guessable admin on a public API | `database/seeders/DatabaseSeeder.php`; the admin comes from env vars (Phase 2) | 2026-11-11 |
| `composer audit` in CI | A dependency with a known vulnerability fails the build before it reaches the server | `.github/workflows/tests.yml` | 2026-11-11 |
| Frontend repo is read-only from this repo's Claude sessions (edits and `.env*` reads denied) | We never change the other side's code; its secrets stay unread | `.claude/settings.json` | 2026-11-11 |
| One generic message for a wrong username or a wrong password | Doesn't tell an attacker which usernames exist | `LoginRequest::WRONG_CREDENTIALS` | 2026-11-11 |
| A failed login always takes ≥ 400 ms (`Timebox`), the success returns at once | The response time can't reveal whether the username exists | `LoginRequest::authenticate()`; test with `Sleep::fake()` | 2026-11-11 |
| Rate limit on **failed** logins only, per username + client IP, cleared on success | Slows guessing without locking out an owner who logs in on several devices | `LoginRequest` | 2026-11-11 |
| The client IP is trusted from a header only with a shared secret (`X-Frontend-Key`, `hash_equals`) and only if it's a valid IP | The API is public: a plain `X-Client-IP` / `X-Forwarded-For` could be faked by anyone to dodge the limit. Server-to-server, all dashboard traffic would otherwise share Vercel's IP | `App\Support\FrontendKey::clientIp()` (used by `LoginRequest` and `throttle:public`), `config/services.php` | 2026-11-11 |
| Admin created by `php artisan admin:create` with a hidden prompt, ≥ 12 characters, confirmed; never a CLI option or a seeder | No plain-text password in `.env` or the shell history; the command is also the password reset | `app/Console/Commands/CreateAdminCommand.php` | 2026-11-11 |
| The seed export refuses a branch staff number that isn't one of the restaurant's public numbers | Real staff numbers are private (never shown on the site): if one ever reaches the mock data, it can't end up committed in this repo | `database/seeders/data/export.mjs` | 2026-11-11 |
| The seed export refuses a mock file that imports anything but types (checked on the text, before running it) | Exporting must never execute frontend code; the mock files stay plain data | `export.mjs` | 2026-11-11 |
| A new password deletes every token of that user | If the password changed because it leaked, every device the attacker used is logged out | `CreateAdminCommand` | 2026-11-11 |
| Tokens expire (7 days) and are pruned daily; Bearer only (`sanctum.guard = []`) | A stolen token stops working; the token table doesn't grow; no session guard is consulted | `config/sanctum.php`, `routes/console.php` | 2026-11-11 |
| Logout deletes only the current device's token | Logging out the laptop doesn't log out the owner's phone | `AuthController::logout()` | 2026-11-11 |
| Throttle keys are hashed | Usernames aren't stored in plain text in the cache table | `LoginRequest::throttleKey()` | 2026-11-11 |
| Uploads: type detected from the **content**, size and pixel limits, **no SVG**, random file names | A renamed script or HTML file can't be served from our domain; SVG can run scripts; huge images ("decompression bombs") are refused; a file name can't collide or leak the owner's file names | `StoreUploadRequest`, `UploadController` | 2026-11-11 |
| Nested ids sent by the client are checked against the parent (a size id must be **this** product's), and the write is also scoped to the parent | The classic IDOR / broken access control bug: saving one product could otherwise rewrite another product's sizes. Two layers: `422` in the request, `whereBelongsTo` in the action | `SaveProductRequest::checkSizes()`, `SaveProduct::syncSizes()` | 2026-11-11 |
| A rule that protects data the frontend doesn't check yet is still enforced by the API (a quick price change can't make a size cost ≤ 0) | The API is the source of truth (BACKEND_REQUIREMENTS §4); the frontend is told so it can add the same check | `PatchProductRequest` | 2026-11-11 |
| Image fields accept only our own upload URLs | Nobody can point menu photos at another host, or at a file outside `uploads/` (`..` refused) | `OwnUploadUrl`, `PublicImage::pathFromUrl()` | 2026-11-11 |
| **The demo server only answers callers with the shared key** (`API_REQUIRE_FRONTEND_KEY`: `X-Frontend-Key` on every `/api/v1` request, `403` otherwise; `/up` open). Fails closed | Keeps a demo on the public internet private to her Next.js server and our Postman. Suwayda's staging uses Basic Auth instead, but that takes the `Authorization` header our Bearer tokens need (decision 5.6) | `RequireFrontendKey`, `App\Support\FrontendKey` (the one place the key is compared, with `hash_equals`); `RequireFrontendKeyTest` | 2026-11-11 |
| Public routes rate limited: 300 / minute per client (`throttle:public`), the browser's IP when the frontend vouches for it | Anyone can call `/menu` directly and load a shared-hosting server; per vouched browser so one visitor can't use up the frontend's allowance (decision 5.5) | `AppServiceProvider::configureRateLimiting()`, `routes/api.php`; `AppServiceProviderTest` | 2026-11-11 |
| On Hostinger the code sits **outside the web root**: a sibling `laravel/` folder, `public_html/` holds only a shim and symlinks | `.env`, `vendor/`, `storage/logs` can't be served even by a mistake in `.htaccess` (decision 5.1, from Suwayda-Under-Siege) | [DEPLOYMENT.md](DEPLOYMENT.md) §1, §3.4 | 2026-11-11 |
| `public/.htaccess` denies `.md .sh .yml .lock .sql .log .env .ini …`, and `public_html/.htaccess` is a **symlink** to it | A stray dump or log in the web root isn't served. In Suwayda the server copy became a real file and later repo changes silently stopped applying; a symlink keeps the reviewed copy the live one | `public/.htaccess`; DEPLOYMENT.md §3.4, §3.8 (tested with a real file) | 2026-11-11 |
| The server pulls with a **read-only deploy key of its own** (an SSH host alias per repo) | A compromised server can't push code; GitHub allows a key on one repo only, and Suwayda's key already owns `Host github.com` | DEPLOYMENT.md §3.3 | 2026-11-11 |
| `bin/deploy.sh` refuses a dangerous `.env` before changing anything: `APP_DEBUG=true`, `APP_ENV` local/testing/empty, an empty `APP_KEY`, the gate on with no key | Debug pages show `.env` values; the other cases break the server or refuse every request. Checked by the script, not by memory | `bin/deploy.sh` `check_env()` | 2026-11-11 |
| Server `.env` from a committed `.env.staging.example` with placeholders, `chmod 600`; `.env.staging` ignored by git; `APP_KEY` generated on the server | The real values never pass through git or a laptop; the template documents every key | `.env.staging.example`, `.gitignore`, DEPLOYMENT.md §3.5 | 2026-11-11 |
| A database user of its own for the demo (never root, never the other site's database) | A leaked demo `.env` reaches only demo data | DEPLOYMENT.md §3.1 | 2026-11-11 |

### Data integrity

| Practice | Why | Where | Since |
|---|---|---|---|
| Foreign keys with deliberate delete rules (restrict / cascade / null on delete) | The database is the backstop when code forgets a check | migrations; [DATABASE.md](DATABASE.md) §4; `tests/Feature/Database/SchemaRulesTest.php` | 2026-11-11 |
| Unique slugs per table, plus a rule across products and offers | Public URLs and QR codes must never collide or change by themselves | migrations; validation rule (Phase 4/6) | 2026-11-11 |
| Lists inside a record saved by id, never deleted and recreated | Carts, ordering settings and printed QR codes point at those ids | sync actions (Phases 4, 10) | 2026-11-11 |
| Images stored as paths, the URL built by the Resource | A host or domain change (staging → production) breaks no stored data | [DATABASE.md](DATABASE.md) §1 | 2026-11-11 |
| Multi-row changes (moves, syncs, site-info save) in one `DB::transaction()` | Never half saved | actions (Phases 4–11) | 2026-11-11 |
| **The seeder refuses a database that already has content** (any row in a content table), before writing anything | `db:seed` on staging or production can never mix with or overwrite the owner's edits; locally `migrate:fresh --seed` still works | `DatabaseSeeder::ensureDatabaseIsEmpty()`; `SeedDataTest` | 2026-11-11 |
| Seeding runs in one transaction; the photos it copied are deleted if it fails | A failed seed leaves nothing half done, on the disk either | `DatabaseSeeder`, `SeedImages::deleteWritten()` | 2026-11-11 |
| Seeded photos are registered as uploads | `OwnUploadUrl` accepts them when the owner re-saves a record, and `uploads:prune` keeps them | `SeedImages` | 2026-11-11 |

### Safety switches

| Practice | Why | Where | Since |
|---|---|---|---|
| `Model::shouldBeStrict()` outside production | Turns silent bugs into errors while developing: lazy loading (N+1), a non-fillable attribute dropped, reading an unselected column. Off in production so a missed case logs instead of breaking the live site | `AppServiceProvider`; `tests/Feature/Providers/AppServiceProviderTest.php` | 2026-11-11 |
| `DB::prohibitDestructiveCommands()` in production | One `migrate:fresh` / `db:wipe` (or `rollback`) in the wrong SSH terminal would wipe the live menu. Production changes go forward with a new migration | `AppServiceProvider`; same test | 2026-11-11 |
| Sessions `array`, queue `sync` by default (in config, not only `.env`) | No session or queue tables exist; a server `.env` that forgets the line must not break | `config/session.php`, `config/queue.php` | 2026-11-11 |
| Arabic locale as the config default, not only in `.env` | A production `.env` that forgets `APP_LOCALE` still answers in Arabic | `config/app.php` | 2026-11-11 |
| Servers run with `APP_DEBUG=false` and daily logs kept 14 days | Debug pages leak config; logs fill a shared-hosting disk | `.env.staging.example`; enforced by `bin/deploy.sh` | 2026-11-11 |
| The demo runs `APP_ENV=staging`, not `production` | Strict mode keeps catching N+1 and fillable mistakes on a real server, and the demo can be reset with `migrate:fresh --seed` (production refuses it) (decision 5.2) | `.env.staging.example`, DEPLOYMENT.md §5 | 2026-11-11 |

### API behaviour

| Practice | Why | Where | Since |
|---|---|---|---|
| Errors under `api/*` always JSON, even without `Accept: application/json` | A caller that forgets the header gets a `422`/`401`, not a redirect to a web page | `bootstrap/app.php`; `tests/Feature/Api/FoundationTest.php` | 2026-11-11 |
| Guests get a JSON `401`, never a redirect to `route('login')` | Laravel 13's default would throw a `500` here (no login route in an API) | `bootstrap/app.php` | 2026-11-11 |
| Versioned base path `/api/v1` | A breaking change later becomes `/v2` without breaking the live site | `bootstrap/app.php` (`apiPrefix`) | 2026-11-11 |
| The contract is the source of truth; changes go through a PR on `API-CONTRACT.md` first | Two repos, one interface: nothing changes silently | CLAUDE.md | 2026-11-11 |

### Testing and CI

| Practice | Why | Where | Since |
|---|---|---|---|
| Tests on MySQL (not SQLite) | Foreign keys, JSON columns and unique indexes behave like production | `phpunit.xml`; CI MySQL 8.0 service | 2026-11-11 |
| CI on every PR: Pint, `composer audit`, Pest | A broken or badly formatted change can't be merged unnoticed | `.github/workflows/tests.yml` | 2026-11-11 |
| CI PHP version = the real minimum (8.4) | `"php": "^8.3"` claimed a version the lock file can't install on | `composer.json`, CI matrix | 2026-11-11 |
| A safety test is checked by breaking the code once (it must fail without the protection) | A test that passes either way protects nothing | e.g. the `migrate:fresh` test | 2026-11-11 |
| Public lists have a **query-count test** (`expectsDatabaseQueryCount`) on a menu big enough to show N+1 | An N+1 query only hurts with real data, on shared hosting; the test fails the day someone adds a lazy relation | `MenuControllerTest` | 2026-11-11 |
| Inverse relations set with `chaperone()` instead of an extra query | Each product needs its category's slug; `chaperone()` gives it for free (and strict mode throws if it's missing) | `Category::products()` | 2026-11-11 |
| 422 keys with dots (`optionGroups.0.options.1.name`) are asserted by reading `errors` directly | `assertJsonPath` treats dots as nesting and finds nothing | `ProductControllerTest` | 2026-11-11 |
| Tests run with `APP_DEBUG=false` | Error bodies are checked in the shape production sends (`{ message }`), never with a stack trace the frontend will never see | `phpunit.xml` | 2026-11-11 |
| A test for our own normalization must use input only our code changes | Laravel's `TrimStrings` already trims outer spaces and the MySQL collation ignores trailing ones: a "  name " test passed with our code removed. Found by breaking the code once | `CategoryControllerTest` (inner spaces) | 2026-11-11 |
| Content-type tests use **real files**, not `UploadedFile::fake()` | A fake reports its type from the file name, so "text renamed to .png" would pass a fake test and still be refused (or worse, accepted) on the server | `realUpload()` in `UploadControllerTest` | 2026-11-11 |

### Process

| Practice | Why | Where | Since |
|---|---|---|---|
| Branch per phase + PR; never push to `main` directly | Every change is reviewed and goes through CI | CLAUDE.md | 2026-11-11 |
| Discuss before coding; options and trade-offs for anything hard to reverse | Schema, dependencies and the contract are expensive to undo | CLAUDE.md | 2026-11-11 |
| Default migrations edited in place only while nothing is deployed | After the first deploy, every change is a new migration (deployed migrations are immutable) | migrations | 2026-11-11 |
| **Seed data is committed** (JSON + the photos it uses + the frontend commit it came from), re-exported by a script, reviewed as a diff | The server never needs the frontend repo or Node; every change to the starting content is visible in a PR; the export is deterministic, so an unchanged mock gives no diff | `database/seeders/data/`, `composer seed:export` | 2026-11-11 |
| Seed tests read their expected values from the same JSON, the way the frontend builds them | The test follows a re-export (no copy of the data to keep in sync), and still checks the seeder independently (order, remapping, best-seller numbering) | `SeedDataTest` | 2026-11-11 |
| Project rules in `CLAUDE.md` never contain Boost's opening tag | `boost:update` treats its first occurrence as the start of its block and wipes everything after it | CLAUDE.md note | 2026-11-11 |
| Deploys are manual and guarded: `main` → `staging` with `--ff-only`, then `bash bin/deploy.sh` over SSH; the server resets to `origin/<branch>` | The demo runs exactly what was reviewed and passed CI; a failed step is visible in the terminal; edits on the server can't drift (decision 5.3) | `bin/deploy.sh`, DEPLOYMENT.md §2, §4 | 2026-11-11 |
| `bin/deploy.sh` runs inside a `main()` function | Bash reads a script as it runs, and `git reset --hard` rewrites this file: a function is parsed whole first, so the new version never runs half old, half new | `bin/deploy.sh` | 2026-11-11 |
| CI also runs on pushes to `staging`, cancels superseded runs, and has read-only permissions | What the demo runs is tested; no wasted runs; the workflow token can't write to the repo | `.github/workflows/tests.yml` | 2026-11-11 |

## To do (agreed, not done yet)

| Practice | Why | Who / when |
|---|---|---|
| Protect `main` on GitHub: require a PR and the `tests` check before merging, no force pushes | Turns "never push to `main`" from a habit into a rule GitHub enforces | The repo owner, in GitHub settings (Settings → Rules → Rulesets, or Branches → Add rule). On a private repo with a free plan GitHub may not offer it: then it stays a habit |
| **Production on its own hosting account** | On shared hosting every site of an account runs as the same Linux user: a hole in one site can read the other's `.env`. Accepted for the demo only (seed data, no real data) | Phase 12, once the owner buys |
| Separate MySQL user for production, never `root`, limited to its own database | A leaked demo `.env` can't touch production | Phase 12 |
| Daily database backups (check Hostinger's, add a dump if not daily), plus `storage/app/public/uploads` | The menu, settings and photos exist only there. Not needed on the demo (seed data, reset with `migrate:fresh --seed`) | Phase 12 |
| Tests on the production database engine | CI tests MySQL 8.0; the production host's engine is decided when it's bought (hPanel can label MariaDB as "MySQL") | Phase 12 |
| Cache the public responses on the API side (e.g. 60 s, cleared on every admin write) | Next.js already caches them, so not needed now; worth it only if the demo shows `/menu` is slow on Hostinger | If measured on the demo |
| Strip EXIF (GPS, camera) from JPEG uploads | The dashboard re-encodes photos to WebP in the browser (no EXIF), but a JPEG sent straight to the API keeps it. Low risk (admin only); needs an image library (GD/Imagick on Hostinger to check) | Later, if direct uploads become common |
| Rotate `FRONTEND_API_KEY` if it ever leaks (new value in both env vars) | A leaked key lets someone past the demo's gate and fake the IP used for the rate limits | Whoever notices; procedure in [DEPLOYMENT.md](DEPLOYMENT.md) §7 |
| Shell (`Bash`) deny rules, or the sandbox, for the frontend repo | Only the Read/Edit tools are blocked today; a shell command could still read its `.env` or commit there | Proposed, waiting for a decision (PROJECT_STATUS open question 5) |

## Considered and not adopted

| Practice | Why not (for now) |
|---|---|
| `laravel-lang` package for Arabic validation texts | A dependency for texts we mostly override per field (decision 0.2) |
| Unique indexes on `position` columns | A move would need a temporary-value swap; the actions keep 1…n in a transaction and tests check it (decision 1.3) |
| Admin account from `ADMIN_USERNAME` / `ADMIN_PASSWORD` env vars in a seeder | Leaves the password in plain text in the server's `.env` (decision 2.1 → `admin:create`) |
| Trusting `X-Forwarded-For` (or Vercel as a trusted proxy) for the login IP | Vercel's outgoing IPs aren't fixed, and a public API can't tell a real proxy from an attacker (decision 3.3 → shared-secret header) |
| Slugs transliterated from Arabic (`Str::slug`) | Tested on the real menu: `شاورما جاج` → `shaorma-gag`, `فروج` → `frog`. Public URLs and QR codes deserve better (decision 4.1 → optional `slug`, otherwise a neutral id) |
| One `PATCH` for both full saves and quick changes | Her forms drop empty optional keys, so a photo or the sizes could never be removed (decision 4.2 → `PUT` full, `PATCH` quick) |
| `throttle` middleware for the login limit | Counts successful logins too: the owner could lock themselves out with a few devices (decision 3.2) |
| Basic Auth (htpasswd) in front of the demo, as Suwayda's staging has | Basic Auth and Bearer tokens share the `Authorization` header, so login would break (decision 5.6 → the frontend-key gate) |
| hPanel Git integration / webhook auto-deploy | Hard to debug when it fails silently, and no guard rails; the manual `bin/deploy.sh` shows every step (decision 5.3) |
| Debug output on staging (Suwayda allows it behind Basic Auth) | Nothing hides it here; `bin/deploy.sh` refuses `APP_DEBUG=true` on any server |
| A separate hosting account for the demo | Not worth paying for before the owner buys: the demo holds seed data only. Production gets its own account (To do) |
| MariaDB in CI | The demo uses MySQL (decision 5.4); revisit if `SELECT VERSION()` on the server says MariaDB, or for the production host |
