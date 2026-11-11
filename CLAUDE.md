# CLAUDE.md — restaurant-api

Laravel 13 REST API (API only, no web pages) for the Ahl Al Karam (أهل الكرم) restaurant website. The frontend is a separate Next.js repo, at `../restaurant-template` on this machine.

> Keep project rules **above** the `<laravel-boost-guidelines>` block: Boost regenerates that block when it updates.

## Start here

1. **[docs/PROJECT_STATUS.md](docs/PROJECT_STATUS.md):** what this is, the decisions, the current state, and what's next. Read it at the start of every session.
2. **[docs/BACKEND_PLAN.md](docs/BACKEND_PLAN.md):** the phases (0–12), what each delivers, the validation to port, and the open decisions. Work one phase at a time and update its status.
3. **The agreed API contract:** `../restaurant-template/.local-docs/API-CONTRACT.md`, the **source of truth** for endpoints, JSON shapes, limits and errors, with `BACKEND_REQUIREMENTS.md` next to it. Read the section relevant to the task. If code and contract disagree, the contract wins: point out the conflict and don't change behaviour silently.
4. [docs/LARAVEL_API_RECAP.md](docs/LARAVEL_API_RECAP.md): how Sanctum, Resources and FormRequests fit this project, and the Hostinger notes.

## The frontend repo is read-only

- Read `../restaurant-template` freely for the contract, `src/types/` (JSON shapes), `src/data/mock/` (seed data) and `src/lib/dashboard/validation.ts` (rules and Arabic messages). `.claude/settings.json` blocks edits there and blocks reading its `.env` files.
- **Never edit, commit or push in the frontend repo from here.** A contract change is proposed to the user. He opens the PR on `API-CONTRACT.md` himself (the Claude GitHub integration has no write access there), and the frontend reviews it.

## Rules that are easy to get wrong

- **Server to server only:** only the Next.js server calls this API, so there's no CORS, no CSRF and no Sanctum SPA cookies. Auth is Sanctum Bearer tokens valid 7 days, for one admin user.
- **JSON:** camelCase keys exactly as in the frontend types, responses **not wrapped** in `data`, empty optional fields **left out**, ids always **strings**. Slugs are the public `id` of categories, products, offers and branches.
- **Lists inside a record are saved by id, never deleted and recreated:** sizes, phones, branches. Cart lines, ordering settings and printed QR codes point at those ids.
- **Validation messages are always Arabic** (ported from the frontend's `validation.ts`). A cross-record rule that refuses a change returns `409` with an Arabic message.
- **No orders, payments, customer accounts or multi-tenancy.** Orders go to WhatsApp and are never stored.
- **Shared hosting (Hostinger):** no Redis, no queue workers. Use the database/file drivers for cache and rate limiting, and cron for the one daily job.

## Working with the user

- He is the backend developer, experienced with full-stack Laravel (Blade, Tailwind, Alpine). This is his first API-only backend for a React/Next.js frontend. Explain the API and Next.js-side reasoning (server-to-server calls, caching, what the frontend expects); skip Laravel basics he already knows.
- **Discuss before coding:** before any code change, briefly explain what you'll do, why, and which files change, then **wait for his approval**. Present options and trade-offs for anything hard to reverse: the schema, a new dependency, a contract change.
- Don't expand scope or start the next step without approval.
- After a feature: list the files changed and what to test, run the tests and Pint, and update `docs/PROJECT_STATUS.md`.
- **Git:** never commit, push or merge unless he asks. Work on branches with PRs, and don't push to `main` directly.
- **Secrets:** never put credentials, tokens, private phone numbers or `.env` values in code, docs or commits.
- Communicate in English unless he asks otherwise. Docs and code are in English; user-facing messages are in Arabic.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.4. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
