#!/usr/bin/env bash
# restaurant-api: idempotent Laravel deploy for Hostinger shared hosting.
# Modelled on Suwayda-Under-Siege's bin/deploy.sh. Full runbook: docs/DEPLOYMENT.md.
#
# Usage:   bash bin/deploy.sh   (from anywhere inside the server's laravel/ checkout)
# Branch:  whichever branch this working tree has checked out (staging for the demo).
#
# Resets the working tree to origin/<branch>: edits made on the server are
# DISCARDED. That's deliberate: the server is a deploy target, not a workspace.
# It never touches public_html/ (the shim and symlinks are one-time setup).

set -euo pipefail

# Everything runs inside main(), called on the last line. Bash reads a script
# as it goes, and `git reset --hard` below may rewrite this very file: a
# function is parsed whole before it runs, so the new version can't be
# executed half old, half new.
main() {
  cd "$(dirname "$0")/.."

  local branch
  branch="$(git rev-parse --abbrev-ref HEAD)"
  echo "==> restaurant-api deploy on branch '${branch}' at $(date -u +%FT%TZ)"

  if [ "${branch}" = "main" ]; then
    fail "refusing to deploy from 'main'. Check out 'staging' first (docs/DEPLOYMENT.md §2)."
  fi

  check_env

  echo "==> pulling origin/${branch}"
  git fetch --prune origin
  git reset --hard "origin/${branch}"

  echo "==> composer install (no dev packages, optimized autoloader)"
  # The seeders need no dev package (no Faker), so the demo can be seeded as is.
  composer install --no-dev --optimize-autoloader --no-interaction

  echo "==> migrate"
  php artisan migrate --force --no-interaction

  echo "==> rebuilding caches (config, routes, events)"
  php artisan optimize:clear
  php artisan optimize

  echo "==> storage link"
  # `|| true`: the link already existing returns non-zero.
  php artisan storage:link || true

  echo "==> writable folders"
  chmod -R 775 storage bootstrap/cache

  echo "==> deploy complete on '${branch}' at $(date -u +%FT%TZ)"
  echo "Next: the smoke tests in docs/DEPLOYMENT.md §3.8."
}

# One value from .env, without quotes or a Windows \r.
env_value() {
  grep -E "^$1=" .env | head -1 | cut -d= -f2- | tr -d '"' | tr -d "'" | tr -d '\r' || true
}

# Refuses a .env that would leak or break: checked before anything changes.
check_env() {
  echo "==> .env checks"

  if [ ! -f .env ]; then
    fail ".env missing. Copy .env.staging.example to .env and fill it in (docs/DEPLOYMENT.md §3.5)."
  fi

  local app_env app_debug app_key require_key frontend_key
  app_env="$(env_value APP_ENV)"
  app_debug="$(env_value APP_DEBUG)"
  app_key="$(env_value APP_KEY)"
  require_key="$(env_value API_REQUIRE_FRONTEND_KEY)"
  frontend_key="$(env_value FRONTEND_API_KEY)"

  case "${app_env}" in
    local | testing | "")
      fail "APP_ENV='${app_env}' on a server. Use staging (demo) or production." ;;
  esac

  # No Basic Auth can sit in front of this API (it would take the Authorization
  # header the Bearer tokens use), so debug output would reach anyone.
  if [ "${app_debug}" = "true" ]; then
    fail "APP_DEBUG=true on a server: error pages would show stack traces and .env values. Set APP_DEBUG=false."
  fi

  if [ -z "${app_key}" ]; then
    fail "APP_KEY is empty. Generate it first (docs/DEPLOYMENT.md §3.6)."
  fi

  if [ "${require_key}" = "true" ] && [ -z "${frontend_key}" ]; then
    fail "API_REQUIRE_FRONTEND_KEY=true with an empty FRONTEND_API_KEY would refuse every request. Set the key."
  fi
}

fail() {
  echo "ERROR: $1" >&2
  exit 1
}

main "$@"
