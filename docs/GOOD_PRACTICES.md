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
| The client IP is trusted from a header only with a shared secret (`X-Frontend-Key`, `hash_equals`) and only if it's a valid IP | The API is public: a plain `X-Client-IP` / `X-Forwarded-For` could be faked by anyone to dodge the limit. Server-to-server, all dashboard traffic would otherwise share Vercel's IP | `LoginRequest::clientIp()`, `config/services.php` | 2026-11-11 |
| Admin created by `php artisan admin:create` with a hidden prompt, ≥ 12 characters, confirmed; never a CLI option or a seeder | No plain-text password in `.env` or the shell history; the command is also the password reset | `app/Console/Commands/CreateAdminCommand.php` | 2026-11-11 |
| A new password deletes every token of that user | If the password changed because it leaked, every device the attacker used is logged out | `CreateAdminCommand` | 2026-11-11 |
| Tokens expire (7 days) and are pruned daily; Bearer only (`sanctum.guard = []`) | A stolen token stops working; the token table doesn't grow; no session guard is consulted | `config/sanctum.php`, `routes/console.php` | 2026-11-11 |
| Logout deletes only the current device's token | Logging out the laptop doesn't log out the owner's phone | `AuthController::logout()` | 2026-11-11 |
| Throttle keys are hashed | Usernames aren't stored in plain text in the cache table | `LoginRequest::throttleKey()` | 2026-11-11 |
| Uploads: type detected from the **content**, size and pixel limits, **no SVG**, random file names | A renamed script or HTML file can't be served from our domain; SVG can run scripts; huge images ("decompression bombs") are refused; a file name can't collide or leak the owner's file names | `StoreUploadRequest`, `UploadController` | 2026-11-11 |
| Nested ids sent by the client are checked against the parent (a size id must be **this** product's), and the write is also scoped to the parent | The classic IDOR / broken access control bug: saving one product could otherwise rewrite another product's sizes. Two layers: `422` in the request, `whereBelongsTo` in the action | `SaveProductRequest::checkSizes()`, `SaveProduct::syncSizes()` | 2026-11-11 |
| A rule that protects data the frontend doesn't check yet is still enforced by the API (a quick price change can't make a size cost ≤ 0) | The API is the source of truth (BACKEND_REQUIREMENTS §4); the frontend is told so it can add the same check | `PatchProductRequest` | 2026-11-11 |
| Image fields accept only our own upload URLs | Nobody can point menu photos at another host, or at a file outside `uploads/` (`..` refused) | `OwnUploadUrl`, `PublicImage::pathFromUrl()` | 2026-11-11 |

### Data integrity

| Practice | Why | Where | Since |
|---|---|---|---|
| Foreign keys with deliberate delete rules (restrict / cascade / null on delete) | The database is the backstop when code forgets a check | migrations; [DATABASE.md](DATABASE.md) §4; `tests/Feature/Database/SchemaRulesTest.php` | 2026-11-11 |
| Unique slugs per table, plus a rule across products and offers | Public URLs and QR codes must never collide or change by themselves | migrations; validation rule (Phase 4/6) | 2026-11-11 |
| Lists inside a record saved by id, never deleted and recreated | Carts, ordering settings and printed QR codes point at those ids | sync actions (Phases 4, 10) | 2026-11-11 |
| Images stored as paths, the URL built by the Resource | A host or domain change (staging → production) breaks no stored data | [DATABASE.md](DATABASE.md) §1 | 2026-11-11 |
| Multi-row changes (moves, syncs, site-info save) in one `DB::transaction()` | Never half saved | actions (Phases 4–11) | 2026-11-11 |

### Safety switches

| Practice | Why | Where | Since |
|---|---|---|---|
| `Model::shouldBeStrict()` outside production | Turns silent bugs into errors while developing: lazy loading (N+1), a non-fillable attribute dropped, reading an unselected column. Off in production so a missed case logs instead of breaking the live site | `AppServiceProvider`; `tests/Feature/Providers/AppServiceProviderTest.php` | 2026-11-11 |
| `DB::prohibitDestructiveCommands()` in production | One `migrate:fresh` / `db:wipe` (or `rollback`) in the wrong SSH terminal would wipe the live menu. Production changes go forward with a new migration | `AppServiceProvider`; same test | 2026-11-11 |
| Sessions `array`, queue `sync` by default (in config, not only `.env`) | No session or queue tables exist; a server `.env` that forgets the line must not break | `config/session.php`, `config/queue.php` | 2026-11-11 |
| Arabic locale as the config default, not only in `.env` | A production `.env` that forgets `APP_LOCALE` still answers in Arabic | `config/app.php` | 2026-11-11 |

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
| Project rules in `CLAUDE.md` never contain Boost's opening tag | `boost:update` treats its first occurrence as the start of its block and wipes everything after it | CLAUDE.md note | 2026-11-11 |

## To do (agreed, not done yet)

| Practice | Why | Who / when |
|---|---|---|
| Protect `main` on GitHub: require a PR and the `tests` check before merging, no force pushes | Turns "never push to `main`" from a habit into a rule GitHub enforces | The repo owner, in GitHub settings (Settings → Rules → Rulesets, or Branches → Add rule). On a private repo with a free plan GitHub may not offer it: then it stays a habit |
| Separate MySQL users for staging and production, never `root`, each limited to its own database | A leaked staging `.env` can't touch production | Phase 5 / 12 |
| `APP_DEBUG=false` and `LOG_CHANNEL=daily` with a retention limit on servers | Debug pages leak config; logs fill a shared-hosting disk | Phase 5 / 12 |
| Daily database backups (check Hostinger's, add a dump if not daily) | The menu and settings exist only in this database | Phase 12 |
| A generous rate limit on the public endpoints (`/menu` and the others), per IP | Anyone can call them directly and load a shared-hosting server. Must stay well above what Vercel's builds and cache refreshes send from one IP (decide the number on staging, Phase 5) | Phase 5 |
| Cache the public responses on the API side (e.g. 60 s, cleared on every admin write) | Next.js already caches them, so not needed now; worth it only if staging shows `/menu` is slow on Hostinger | Phase 5, if measured |
| Strip EXIF (GPS, camera) from JPEG uploads | The dashboard re-encodes photos to WebP in the browser (no EXIF), but a JPEG sent straight to the API keeps it. Low risk (admin only); needs an image library (GD/Imagick on Hostinger to check) | Later, if direct uploads become common |
| Rotate `FRONTEND_API_KEY` if it ever leaks (new value in both env vars) | A leaked key only lets someone fake the IP used for the login limit, but it should still be replaced | Whoever notices; procedure in `docs/DEPLOY.md` (Phase 5) |
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
