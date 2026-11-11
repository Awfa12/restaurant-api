# Deployment — the demo server on Hostinger

> **What this server is for:** showing the product to the restaurant owner. It runs seed data only.
> If he buys, the project gets **its own hosting** for production (§9); nothing here is production.
>
> **Where the method comes from:** the same setup as Suwayda-Under-Siege
> (`C:\Laravel Projects\Suwayda-Under-Siege\docs\operations\DEPLOYMENT.md`), adapted to an API-only
> backend: no Vite, no Filament, no Basic Auth (§3.8 explains why).

Replace the placeholders once before you copy commands. Keep the filled-in copy **off git**.

| Placeholder | Where to find it |
| --- | --- |
| `<port>`, `<user>`, `<host>` | hPanel → Advanced → SSH Access |
| `<demo>` | the demo subdomain (the temporary Hostinger one), e.g. `something.hostingersite.com` |

---

## 1. The layout

Only `public_html/` is served. The code, `.env`, `vendor/` and `storage/` sit next to it, out of reach.
This works even though Hostinger won't let you move the document root.

```
~/domains/<demo>/
├── laravel/                 ← the repo (branch staging), NOT served
│   ├── public/              ← Laravel's public folder
│   ├── storage/
│   ├── .env                 ← chmod 600, never in git
│   └── ...
└── public_html/             ← the document root (served)
    ├── index.php            ← 2-line shim: loads ../laravel/public/index.php
    ├── .htaccess            ← SYMLINK → ../laravel/public/.htaccess
    └── storage/             ← SYMLINK → ../laravel/public/storage (uploaded photos)
```

**Keep `.htaccess` a symlink.** In Suwayda it became a real file, so later changes to the repo copy
silently stopped applying. As a symlink, the reviewed copy in git is the one Apache reads. It also
passes the `Authorization` header to Laravel, which Bearer tokens need.

## 2. Branches

```
feature branch ─PR─► main ─(ff-only)─► staging ─SSH: bash bin/deploy.sh─► <demo>
```

- `main` deploys nowhere. `staging` is exactly what the demo runs.
- Promote with fast-forward merges only. If `--ff-only` fails, stop: someone committed on `staging`.
- `bin/deploy.sh` refuses to run on `main`.
- There is no `production` branch until the owner buys (§9).

## 3. One-time setup

### 3.1 hPanel

1. **Website:** the temporary subdomain `<demo>`. Then turn on **SSL** and **Force HTTPS**.
2. **PHP:** Advanced → PHP Configuration **for `<demo>`**: version **8.4**, `upload_max_filesize` and
   `post_max_size` **≥ 8M** (photos are ≤ 5 MB plus the form).
3. **Database:** Databases → a new MySQL database and **its own user** with all privileges on it,
   never root and never the Suwayda database. Put the password in your password manager.
4. **Check the engine:** phpMyAdmin → `SELECT VERSION();`.
   - `8.x` is MySQL, the same engine as CI.
   - `10.x-MariaDB` / `11.x-MariaDB` means hPanel's "MySQL" is really MariaDB. The demo is
     **11.8.9-MariaDB**. Keep `DB_CONNECTION=mysql`: Laravel's `mysql` driver works with MariaDB, and our
     schema uses nothing that differs (decision 5.4). CI tests on MySQL 8.0, and production must be
     tested on its real engine.

### 3.2 SSH and the PHP version

```bash
ssh -p <port> <user>@<host>
php -v          # must be 8.4.x
composer -V     # Composer 2
```

The hPanel PHP setting only changes the web server's PHP, so the SSH `php` can be older.
If `php -v` isn't 8.4, point `~/bin/php` at the 8.4 binary (Suwayda's DEPLOYMENT.md §9.1). If
Suwayda is on the same account, that's probably done already.

### 3.3 Deploy key (read-only, its own key)

GitHub accepts a key on **one** repo only, and `~/.ssh/config` already has `Host github.com` for
Suwayda's key. So this repo gets a second key and its own host alias:

```bash
ssh-keygen -t ed25519 -C "hostinger-restaurant-api" -f ~/.ssh/id_ed25519_restaurant_api -N ""
cat ~/.ssh/id_ed25519_restaurant_api.pub
```

GitHub → `Awfa12/restaurant-api` → Settings → Deploy keys → Add. Paste it and **leave "Allow write
access" off**. Then append this to `~/.ssh/config` on the server:

```
Host github-restaurant-api
  HostName github.com
  User git
  IdentityFile ~/.ssh/id_ed25519_restaurant_api
  IdentitiesOnly yes
```

```bash
ssh -T git@github-restaurant-api   # → "Hi Awfa12/restaurant-api! You've successfully authenticated"
```

### 3.4 Clone next to `public_html`, then the shim

```bash
cd ~/domains/<demo>
git clone --branch staging --single-branch git@github-restaurant-api:Awfa12/restaurant-api.git laravel

cd ~/domains/<demo>/public_html
ls -la                                   # back up anything you want to keep first
rm -f index.html default.html index.php

cat > index.php <<'PHP'
<?php
// Hostinger shim: boots Laravel from ../laravel, so its code and .env stay out of the web root.
require __DIR__.'/../laravel/public/index.php';
PHP

ln -sf ../laravel/public/.htaccess  .htaccess
ln -sf ../laravel/public/storage    storage    # dangling until storage:link runs in §3.6
```

### 3.5 `.env`

```bash
cd ~/domains/<demo>/laravel
cp .env.staging.example .env
chmod 600 .env
nano .env
```

Fill in every `TODO`:

- `APP_URL=https://<demo>`. Image URLs are built from it.
- The `DB_*` values from §3.1. Use **the host hPanel shows** next to the database.
  - `localhost` connects through a socket file on the web server. It worked on the demo (the
    database runs on the same machine).
  - If it fails with `[2002] No such file or directory`, use `127.0.0.1` or the database server's
    hostname (e.g. `srv2024.hstgr.io`); both connect over TCP. Suwayda's notes say "never
    `localhost`" because it hit that error.
- Put the password in **double quotes** if it has `#`, `$`, a space, `&`, `(`, `)` or `!`. Unquoted, the
  value is cut or expanded, and you get "Access denied".
- `FRONTEND_API_KEY`: generate it on the server with `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`,
  and save it in the password manager.

Keep `APP_ENV=staging`, `APP_DEBUG=false` and `API_REQUIRE_FRONTEND_KEY=true`.
If you pasted from Windows, strip the line endings: `sed -i 's/\r$//' .env`.

### 3.6 First deploy

```bash
cd ~/domains/<demo>/laravel

# APP_KEY with openssl: artisan key:generate can silently do nothing on Hostinger.
KEY="base64:$(openssl rand -base64 32)"
sed -i "s|^APP_KEY=.*|APP_KEY=${KEY}|" .env
grep -c '^APP_KEY=base64:' .env          # → 1

bash bin/deploy.sh                       # composer, migrate, caches, storage:link

php artisan db:seed --force              # once: the mock menu, photos, settings
php artisan admin:create admin           # your admin; it asks for the password (≥ 12 characters)
```

- The seeder refuses a database that already has content, so a second run can't mix with edits.
- If `migrate` fails with "Access denied", the cause is almost always the password quoting in §3.5.

### 3.7 Cron (the daily cleanups)

hPanel → Advanced → Cron Jobs, every minute:

```
cd ~/domains/<demo>/laravel && php artisan schedule:run >> /dev/null 2>&1
```

- It runs `sanctum:prune-expired` and `uploads:prune` once a day.
- Use the full path to the PHP 8.4 binary if the cron's `php` isn't 8.4. Check that with
  `php -v` from a test cron line.
- This is a second line next to Suwayda's, not a replacement for it.

### 3.8 Smoke tests

The gate refuses any `/api/v1` request without `X-Frontend-Key`, so these send it. Set it in your
local shell, never in a file in the repo. PowerShell: `$K = '<the key>'`, and use `curl.exe`.

```bash
curl -s  https://<demo>/up -o /dev/null -w "%{http_code}\n"                                # 200
curl -s  https://<demo>/api/v1/menu -o /dev/null -w "%{http_code}\n"                       # 403 (no key)
curl -s  -H "X-Frontend-Key: $K" -H "Accept: application/json" https://<demo>/api/v1/menu  # the menu JSON

# The Authorization header reaches Laravel: log in, then /auth/me with the token.
TOKEN=$(curl -s -H "X-Frontend-Key: $K" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"<yours>"}' https://<demo>/api/v1/auth/login | sed -E 's/.*"token":"([^"]+)".*/\1/')
curl -s -H "X-Frontend-Key: $K" -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" https://<demo>/api/v1/auth/me
# → {"name":...}. A 401 here means the header was dropped: check the .htaccess symlink.
```

Open one `image` URL from the menu JSON in a browser. If it doesn't load, `storage:link` or the
`public_html/storage` symlink is missing.

These **must not** return 200:

```bash
for p in .env composer.json composer.lock storage/logs/ artisan; do
  printf "%-16s " "$p"; curl -s -o /dev/null -w "%{http_code}\n" "https://<demo>/$p"
done
```

To test the deny block, use a **real** file. A path that doesn't exist proves nothing, because Laravel
rewrites it to `index.php` and answers 404 anyway.

```bash
echo test > ~/domains/<demo>/public_html/zz-test.sql
curl -s -o /dev/null -w "%{http_code}\n" https://<demo>/zz-test.sql    # 403
rm ~/domains/<demo>/public_html/zz-test.sql
```

Postman: import `restaurant-api-staging.postman_environment.json` (next to the collection, outside
the repo). Set `baseUrl` and `rootUrl`, and put the key and password in the **current** values only.
The collection adds `X-Frontend-Key` to every request.

**Why no Basic Auth gate, as Suwayda's staging has:** Basic Auth and our Bearer tokens both use the
`Authorization` header, so a Basic Auth gate would break login. `API_REQUIRE_FRONTEND_KEY` does the
same job (decision 5.6): only her Next.js server and our Postman have the key.

## 4. Everyday deploy

```bash
# Locally, after the PR is merged into main
git checkout staging
git pull --ff-only origin staging
git merge --ff-only main
git push origin staging
git checkout main

# On the server
ssh -p <port> <user>@<host>
cd ~/domains/<demo>/laravel && bash bin/deploy.sh
```

`bin/deploy.sh` stops before changing anything if:

- the branch is `main`
- `.env` is missing
- `APP_ENV` is `local`, `testing` or empty
- `APP_DEBUG=true`
- `APP_KEY` is empty
- the gate is on with no key

Then it does: fetch, `reset --hard origin/<branch>`, `composer install --no-dev`, `migrate --force`,
`composer cache:build`, `storage:link`, `chmod 775`. It is safe to re-run.

`composer cache:build` caches config, routes and events, each replacing its old cache. CI runs the same
script on a fresh checkout. Two commands it deliberately avoids:

- **`php artisan optimize`** also compiles Blade views. This API has no `resources/views`, so it fails
  (the first deploy hit this).
- **`php artisan optimize:clear`** also empties the application cache. With `CACHE_STORE=database`,
  that's the `cache` table holding the login and rate-limit counters, so a deploy would reset them
  (CI caught it: there, no database exists).

## 5. Before a demo: reset to clean data

```bash
cd ~/domains/<demo>/laravel
php artisan migrate:fresh --seed --force     # allowed because APP_ENV=staging
php artisan admin:create admin               # the users table was emptied too
```

- `migrate:fresh` doesn't delete files: earlier uploads stay in `storage/app/public/uploads` until
  `uploads:prune` removes the unused ones (24 hours later).
- **The owner's account:** `php artisan admin:create <his-username>` with a password made for him.
  If he doesn't buy, delete the account or change its password.
- **Tell him what he types isn't kept.** The demo has no backups, and real staff phone numbers don't
  belong on it.

## 6. Rollback

```bash
cd ~/domains/<demo>/laravel
git log --oneline -5
git reset --hard <last-good-sha>
composer install --no-dev --optimize-autoloader --no-interaction
composer cache:build
```

Then put `staging` back to that commit on GitHub, or fix forward on `main`. For a demo, fixing
forward is usually simpler. A broken migration is fixed by the §5 reset.

## 7. What the frontend owner gets (privately, never in a commit or chat log)

- The API base URL: `https://<demo>/api/v1`, and the image host `<demo>` for `images.remotePatterns`.
- `FRONTEND_API_KEY`. Her server sends it as `X-Frontend-Key` **on every request**, because the demo
  refuses requests without it, not only on login.
- An admin account for her tests, made with `admin:create`, separate from yours and the owner's.
- **Vercel's function region:** Hostinger is in Frankfurt, so `fra1` keeps each API call short.

**Rotating `FRONTEND_API_KEY`** (if it leaks or someone leaves the project):

1. Generate a new key on the server and put it in `.env`, then run `php artisan config:cache`.
2. Send it to her privately. She updates `FRONTEND_API_KEY` in Vercel and redeploys.
3. Between steps 1 and 2 the demo refuses her server (`403`), so do this when nobody is demoing.
4. Update your Postman environment's current value.

## 8. Known Hostinger problems

| Symptom | Cause, fix |
| --- | --- |
| Every request returns 500 and there's no log | `.env` missing or unreadable: §3.5. Then `bash bin/deploy.sh`. |
| `SQLSTATE[HY000] [1045] Access denied` | Wrong or unquoted `DB_PASSWORD`, or a user not linked to the database (hPanel → Databases → Manage users). |
| `[2002] No such file or directory` | `DB_HOST=localhost` connects through a socket file the web server doesn't have (the database is elsewhere, or the socket is in another place): use `127.0.0.1` or the database server's hostname. |
| `composer install` complains about the PHP version | The SSH `php` isn't 8.4: §3.2. |
| Login works, `/auth/me` returns 401 | The `Authorization` header is dropped: `public_html/.htaccess` must be the symlink (§3.4). |
| Photos return 404 | `php artisan storage:link`, and the `public_html/storage` symlink. |
| 403 «ما عندك صلاحية» on every call | `X-Frontend-Key` missing or different from `FRONTEND_API_KEY`. After editing `.env`, run `php artisan config:cache`: config is cached. |
| A `.env` change does nothing | Config is cached: `php artisan config:cache`. |
| `bin/deploy.sh` fails at `views` with "resources/views directory does not exist" | An old `deploy.sh` that ran `php artisan optimize` (fixed, see §4). Pull, then run `composer cache:build`, `php artisan storage:link` and `chmod -R 775 storage bootstrap/cache`. |
| `upload` returns 413 or an empty `422` | `upload_max_filesize` / `post_max_size` below 8M for this site (§3.1). |

## 9. When the owner buys: production

Production gets a new hosting account, so nothing is shared with Suwayda (docs/GOOD_PRACTICES.md).
Most of this runbook still applies. On top of it:

- a `production` branch, protected, promoted from `staging` with `--ff-only`
- `.env.production.example`, with `APP_ENV=production` (strict mode off, and destructive commands
  refused)
- `db:seed --force` once, on the empty database; never `migrate:fresh`
- backups of the database and `storage/app/public/uploads`
- tests on the production database engine
- the domain decided with the owner
- whether the frontend-key gate stays on (it can, if every request comes from her server)
