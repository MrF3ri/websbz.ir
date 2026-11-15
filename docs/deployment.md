# Production deployment runbook

This document describes a production topology. It does not mean that a production server has been provisioned or deployed.

## Architecture

- `Dockerfile.production` builds a PHP 8.5 FPM image with production Composer dependencies and Vite assets built by Node 22.
- Nginx serves the built public assets and forwards PHP requests to the private FPM service.
- PostgreSQL is reachable only on the private backend network.
- Queue worker and scheduler run as separate containers.
- `storage_data` persists private payment receipts and Laravel runtime files.
- `postgres_data` persists database state.
- The web container binds to `127.0.0.1:8080` by default. Put a TLS reverse proxy in front of it; do not publish PHP-FPM or PostgreSQL ports.

## First deployment

1. Check out a reviewed release tag on the server. Do not deploy an unreviewed feature branch.
2. Create the production environment file:

   ```sh
   cp .env.production.example .env.production
   chmod 600 .env.production
   ```

3. Set a unique database password, the real HTTPS `APP_URL`, SMTP credentials, and a versioned `APP_IMAGE` tag. Keep `.env.production` out of source control and backups accessible to untrusted users.
4. Build the production image:

   ```sh
   docker compose --env-file .env.production -f compose.production.yaml build app
   ```

5. Generate an application key and copy the printed value into `.env.production`:

   ```sh
   docker compose --env-file .env.production -f compose.production.yaml run --rm --no-deps app php artisan key:generate --show
   ```

6. Start PostgreSQL, then run migrations and seed only the plan catalog. Do not run the general `DatabaseSeeder` in production because it is intended for development/test setup:

   ```sh
   docker compose --env-file .env.production -f compose.production.yaml up -d postgres
   docker compose --env-file .env.production -f compose.production.yaml run --rm app php artisan migrate --force
   docker compose --env-file .env.production -f compose.production.yaml run --rm app php artisan db:seed --class=PlanSeeder --force
   ```

7. Start the application, asset-copy job, web server, queue worker, and scheduler:

   ```sh
   docker compose --env-file .env.production -f compose.production.yaml up -d app assets web worker scheduler
   docker compose --env-file .env.production -f compose.production.yaml exec app php artisan optimize
   ```

8. Configure the TLS reverse proxy to forward to `http://127.0.0.1:8080`. It must overwrite (not blindly trust client-supplied) `X-Forwarded-For`, `X-Forwarded-Host`, `X-Forwarded-Proto: https`, and `X-Forwarded-Port: 443`. The application trusts the proxy headers configured by `TRUSTED_PROXIES`. The sample uses `*` only because the FPM service is not host-published and the Compose networks are private; use fixed trusted proxy addresses if your topology allows it.
9. Verify `https://your-domain/up`, registration, email verification, business onboarding, public menu, QR download, private receipt upload, and admin payment review.
10. After registering and verifying the platform owner's account, grant the single platform-admin flag through the CLI:

    ```sh
    docker compose --env-file .env.production -f compose.production.yaml exec app php artisan platform-admin:grant owner@example.com
    ```

11. Sign in as that account, open `/admin/settings`, and configure the destination bank card before asking customers to submit transfers.

## Required production settings

- `APP_ENV=production`, `APP_DEBUG=false`, a strong unique `APP_KEY`, and an HTTPS `APP_URL`.
- `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, and a suitable `SESSION_SAME_SITE`.
- A unique PostgreSQL password and SMTP credentials that can deliver verification and password-reset emails.
- `TRUSTED_PROXIES` matching the actual reverse-proxy topology.
- A persistent, backed-up `storage_data` volume. Payment receipts are private files and must not be moved to `public` storage.
- Host firewall rules that expose only the TLS reverse proxy. PostgreSQL and PHP-FPM must not be publicly reachable.

## Operations

Useful commands:

```sh
docker compose --env-file .env.production -f compose.production.yaml ps
docker compose --env-file .env.production -f compose.production.yaml logs --tail=200 app web worker scheduler
docker compose --env-file .env.production -f compose.production.yaml exec app php artisan about
docker compose --env-file .env.production -f compose.production.yaml exec app php artisan queue:failed
```

Back up PostgreSQL and private receipt storage together. Example database backup:

```sh
mkdir -p backups
docker compose --env-file .env.production -f compose.production.yaml exec -T postgres sh -lc 'pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB"' > "backups/database-$(date +%F-%H%M%S).sql"
```

Back up private receipts and runtime storage separately:

```sh
docker run --rm \
  -v websbz-production_storage_data:/data:ro \
  -v "$PWD/backups":/backups \
  alpine:3.20 \
  tar czf "/backups/storage-$(date +%F-%H%M%S).tgz" -C /data .
```

The Compose volume named `websbz-production_storage_data` contains private receipts and must be backed up separately. Test restoration before relying on backups. The `public_assets` volume is reproducible from the deployed image.

For an update, deploy an immutable image tag, run reviewed additive migrations, refresh the `assets` volume from that image, and then restart app/web/worker/scheduler. Take a database and storage backup before migrations. Rollback must account for database migration compatibility; reverting the image alone is not sufficient for destructive schema changes.

## Not yet performed

No server credentials, DNS zone, TLS certificate, SMTP account, or production database were provided in this project session. Therefore no production infrastructure has been created, no production migration has been run, and no customer data has been touched.
