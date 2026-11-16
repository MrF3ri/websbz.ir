#!/usr/bin/env sh
set -eu

COMPOSE="docker compose --env-file .env.docker"
TEST_DB_DATABASE="websbz_test"

# Read the development DB name from the running app container, not from an
# assumption about .env.docker. Never allow the test target to equal it.
DEV_DB_DATABASE="$($COMPOSE exec -T app printenv DB_DATABASE | tr -d '\r')"
if [ -z "$DEV_DB_DATABASE" ]; then
  echo "ERROR: unable to determine the development DB_DATABASE from app container." >&2
  exit 1
fi
if [ "$TEST_DB_DATABASE" = "$DEV_DB_DATABASE" ]; then
  echo "ERROR: refusing to run RefreshDatabase against the development database ($DEV_DB_DATABASE)." >&2
  exit 1
fi

# Create the dedicated test database if it is missing. This also supports
# existing named volumes, for which docker-entrypoint-initdb.d is not rerun.
if ! $COMPOSE exec -T postgres sh -lc \
  'psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB" -tAc "SELECT 1 FROM pg_database WHERE datname = '\''websbz_test'\''" | grep -qx 1'
then
  $COMPOSE exec -T postgres sh -lc \
    'psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB" -c "CREATE DATABASE websbz_test"'
fi

$COMPOSE exec -T \
  -e APP_ENV=testing \
  -e DB_CONNECTION=pgsql \
  -e DB_HOST=postgres \
  -e DB_PORT=5432 \
  -e DB_DATABASE="$TEST_DB_DATABASE" \
  -e SESSION_DRIVER=array \
  -e CACHE_STORE=array \
  -e QUEUE_CONNECTION=sync \
  app php artisan test "$@"
