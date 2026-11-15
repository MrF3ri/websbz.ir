# Local Development with Docker Compose

## Why custom Compose instead of Laravel Sail?

Laravel Sail remains installed as a development dependency and is intentionally untouched. This repository uses a small custom Compose topology because the baseline needs an explicit PHP 8.5 FPM runtime, a separate Nginx web service, PostgreSQL health gating/persistence, and an independent Node/Vite development service. The custom setup is project-owned and does not replace or modify Sail.

## Requirements

- Docker Engine and Docker Compose v2.
- PHP 8.5 is provided by the application image.
- Node in CI is pinned to Node 22. The owner's workstation is currently Node 26.13.1 / npm 12.2.2 (ERRATA); the dedicated Vite image uses the Node 22 runtime copied from the official Node image to match CI. The Vite image also includes PHP CLI and Composer-installed dependencies because the Laravel Wayfinder Vite plugin invokes Artisan to generate route/action declarations. The local Node 26 version is not a CI target and no build behavior is changed to accommodate it.
- The Dockerfile installs `pdo_pgsql`, `mbstring`, `intl`, `zip`, and `bcmath`. PHP 8.5 includes Zend OPcache in the PHP binary; the image build explicitly verifies that it is available instead of trying to install it as a separate shared extension.

## First-time setup

Copy the example environment file:

```sh
cp .env.docker.example .env.docker
```

The sample database credentials are local-development-only placeholders. Do not reuse them outside a disposable local environment. Generate an application key and copy the displayed value into `APP_KEY` in `.env.docker`:

```sh
docker compose --env-file .env.docker run --rm app php artisan key:generate --show
```

Build and start the services:

```sh
docker compose --env-file .env.docker up -d --build
```

Wait until PostgreSQL reports healthy, then run migrations:

```sh
docker compose --env-file .env.docker exec app php artisan migrate
```

The web application is served at http://localhost:8000 and Vite at http://localhost:5173.

## Common commands

Start/rebuild:

```sh
docker compose --env-file .env.docker up -d --build
```

Run migrations:

```sh
docker compose --env-file .env.docker exec app php artisan migrate
```

Run the full PHPUnit suite inside the PHP container using the isolated SQLite in-memory database:

```sh
sh scripts/test-docker-sqlite.sh
```

Run the full PHPUnit suite against the dedicated PostgreSQL test database:

```sh
sh scripts/test-docker-pgsql.sh
```

The PostgreSQL runner targets `websbz_test`, never the development database. It checks the active development database name and aborts if the target matches it. It creates `websbz_test` when missing; the Compose Postgres init hook also creates it when a fresh data volume is initialized.

**Critical test-data warning:** Tests use Laravel's `RefreshDatabase` trait. It may drop/recreate or otherwise reset the schema on the database connection used by the test process. It wipes the database it runs against; therefore, run the PostgreSQL test script only against the dedicated disposable `websbz_test` database. Never point it at a development, staging, or production database. The SQLite runner uses `:memory:` and does not touch PostgreSQL.

Build frontend assets:

```sh
docker compose --env-file .env.docker exec vite npm run build
```

Inspect service status/logs:

```sh
docker compose --env-file .env.docker ps
docker compose --env-file .env.docker logs -f app web postgres vite
```

Stop and remove containers/networks while preserving the PostgreSQL named volume:

```sh
docker compose --env-file .env.docker down
```

**Data warning:** Do not add `-v` to the teardown command unless you explicitly intend to permanently delete the named PostgreSQL data volume. The default teardown above preserves database data.

## Notes

- The application source is bind-mounted for live PHP edits. Composer dependencies use a named `vendor` volume; Node dependencies use a named `node_modules` volume.
- PostgreSQL data persists in the `postgres_data` named volume.
- No Redis service is included: the current baseline does not establish a Redis-dependent feature or runtime requirement. Laravel's configured database-backed queue/cache/session drivers remain available.
- The optional queue worker is documented as a commented Compose service and can be enabled when queued workloads are introduced.
- This is a development topology, not a production deployment recipe. Production secrets, TLS termination, backups, monitoring, and hardened runtime configuration require a separate deployment design.

## OWNER VERIFY

- Run the first-time setup and confirm all services start.
- Confirm PostgreSQL becomes healthy and Laravel can connect from the app container.
- Confirm migrations run against PostgreSQL.
- Confirm http://localhost:8000 is reachable and Vite serves on port 5173.
- Run PHPUnit and the frontend production build in the containers.
- Run `docker compose --env-file .env.docker down`, then start again and confirm PostgreSQL data persists.
