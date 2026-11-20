#!/usr/bin/env bash
# Local-only DCMS development server.
#
# Uses the repo-local PHP toolchain, a disposable SQLite database, and a
# generated local sign-in account. Nothing here is used in production: the
# account command refuses production and non-SQLite databases.
#
#   bash backend/scripts/dev-local.sh                # prepare and serve on 127.0.0.1:8000
#   bash backend/scripts/dev-local.sh --prepare-only # prepare the database and account, then exit
#   bash backend/scripts/dev-local.sh --serve-only   # serve without touching the database
#   bash backend/scripts/dev-local.sh --notifications-only # refresh the sample notifications only
#
# The generated password is written to backend/.env.dev-local (gitignored).

set -euo pipefail

BACKEND="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
REPO="$(cd "$BACKEND/.." && pwd)"
cd "$BACKEND"

PREPARE=1
SERVE=1
NOTIFY_ONLY=0
for arg in "$@"; do
  case "$arg" in
    --prepare-only) SERVE=0 ;;
    --serve-only) PREPARE=0 ;;
    --notifications-only) PREPARE=0; SERVE=0; NOTIFY_ONLY=1 ;;
    *) echo "Unknown option: $arg" >&2; exit 2 ;;
  esac
done

PHP_BIN="${PHP_BIN:-$REPO/.local-tools/php-8.5.12/php.exe}"
if [ ! -x "$PHP_BIN" ]; then
  PHP_BIN="$(command -v php || true)"
fi
if [ ! -x "$PHP_BIN" ]; then
  echo "PHP 8.4+ was not found. Set PHP_BIN to a PHP 8.4 or 8.5 executable." >&2
  exit 1
fi
if ! "$PHP_BIN" -r 'exit(PHP_VERSION_ID >= 80401 ? 0 : 1);'; then
  echo "$($PHP_BIN -v | head -1) is too old. DCMS needs PHP 8.4.1 or newer." >&2
  exit 1
fi

if [ -z "${PHPRC:-}" ] && [ -f "$REPO/.local-tools/php-8.5.12/php-test.ini" ]; then
  export PHPRC="$REPO/.local-tools/php-8.5.12/php-test.ini"
fi

DB_FILE="$BACKEND/database/local-dev.sqlite"
DB_PATH="$DB_FILE"
if command -v cygpath >/dev/null 2>&1; then
  DB_PATH="$(cygpath -m "$DB_FILE")"
fi

export APP_ENV=local
export APP_DEBUG=true
export APP_URL=http://127.0.0.1:8000
export DB_CONNECTION=sqlite
export DB_DATABASE="$DB_PATH"
export SESSION_DRIVER=file
export CACHE_STORE=file
export QUEUE_CONNECTION=sync
export SESSION_DOMAIN=
export DCMS_DEV_EMAIL="${DCMS_DEV_EMAIL:-admin@dcms.local}"
export DCMS_DEV_NAME="${DCMS_DEV_NAME:-Local Test Administrator}"

CREDENTIALS="$BACKEND/.env.dev-local"
if [ ! -f "$CREDENTIALS" ]; then
  if command -v openssl >/dev/null 2>&1; then
    GENERATED="$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-24)"
  else
    GENERATED="$(node -e "console.log(require('crypto').randomBytes(18).toString('base64url'))")"
  fi
  umask 077
  printf 'DCMS_DEV_EMAIL=%s\nDCMS_DEV_PASSWORD=%s\n' "$DCMS_DEV_EMAIL" "$GENERATED" > "$CREDENTIALS"
fi
# shellcheck disable=SC1090
set -a
. "$CREDENTIALS"
set +a

if [ "$NOTIFY_ONLY" -eq 1 ]; then
  if [ ! -f "$DB_FILE" ]; then
    echo "The local database is missing. Run backend/scripts/dev-local.sh --prepare-only first." >&2
    exit 1
  fi
  "$PHP_BIN" artisan dcms:dev-account     --email="$DCMS_DEV_EMAIL"     --name="$DCMS_DEV_NAME"     --with-notifications     --role=KMT     --no-interaction
  exit 0
fi

if [ "$PREPARE" -eq 1 ]; then
  touch "$DB_FILE"
  "$PHP_BIN" artisan migrate --force --no-interaction
  "$PHP_BIN" artisan cache:clear --no-interaction
  "$PHP_BIN" artisan db:seed --class=OnpRolePermissionSeeder --force --no-interaction
  "$PHP_BIN" artisan dcms:dev-account \
    --email="$DCMS_DEV_EMAIL" \
    --name="$DCMS_DEV_NAME" \
    --with-notifications \
    --role=KMT \
    --no-interaction
  echo "Local sign-in: $DCMS_DEV_EMAIL (password in backend/.env.dev-local)"
fi

if [ "$SERVE" -eq 1 ]; then
  echo "DCMS API on http://127.0.0.1:8000 using database/local-dev.sqlite"
  exec "$PHP_BIN" -S 127.0.0.1:8000 -t public
fi
