#!/usr/bin/env sh
set -eu

# Run the full suite against an in-memory SQLite database, isolated from the
# PostgreSQL development database configured in .env.docker.
docker compose --env-file .env.docker exec -T \
  -e APP_ENV=testing \
  -e DB_CONNECTION=sqlite \
  -e DB_DATABASE=:memory: \
  -e SESSION_DRIVER=array \
  -e CACHE_STORE=array \
  -e QUEUE_CONNECTION=sync \
  app php artisan test
