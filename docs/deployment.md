# Production deployment runbook

Last reconciled: 2026-11-17.

> **Existing production warning:** `websbz.ir` is already deployed on the owner's Ubuntu VPS. The first-deployment procedure below is retained as historical reference for a genuinely new environment. Do not run it against the existing production host as a setup checklist. In particular, do not regenerate `APP_KEY`, recreate the database, rerun seeders, remove volumes, or restart unrelated legacy services. For the existing host, use the operational and release guidance below and take verified backups before any schema or deployment change.

This runbook distinguishes the already-provisioned production environment from the remaining operational work. It does not contain production credentials.

## Current production architecture

The deployed topology recorded during the owner-assisted setup is:

- Public domain: `websbz.ir` and `www.websbz.ir`.
- Caddy runs on the VPS as the public TLS reverse proxy and forwards requests to `http://127.0.0.1:8080`.
- The production Compose stack contains the Laravel/PHP-FPM application, Nginx, PostgreSQL, queue worker, scheduler, and a one-shot asset-copy service.
- Nginx serves public assets and forwards PHP requests to the private FPM service.
- PostgreSQL is reachable through the private Compose backend network, not as a public service.
- The web container is published on host loopback at `127.0.0.1:8080:80`.
- Persistent Compose volumes hold PostgreSQL state and application storage, including private payment receipts.
- The production app uses `APP_ENV=production`, `APP_DEBUG=false`, HTTPS `APP_URL`, and secure session-cookie settings.
- SMTP uses Resend (`smtp.resend.com`); the owner confirmed domain verification and a successful delivery test. Keep API keys and SMTP credentials only in the protected production environment file; never place them in this runbook or Git.
- Initial production migrations and plan catalog seeding were completed during provisioning.

The host also runs legacy services unrelated to this application. They are explicitly out of scope: do not stop, recreate, or reconfigure them during websbz deployment work.

The architecture above reflects the setup session. Before operational changes, verify the live service state using the commands below without printing environment values or secrets.

## First deployment — historical procedure only

Use this section only when provisioning a genuinely new, empty production environment. It is not the procedure for the already-running `websbz.ir` host. Never repeat these steps against the existing production database or volumes.

1. Check out a reviewed release tag on the new server. Do not deploy an unreviewed feature branch.
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

## Existing production operations

Start with read-only checks. These commands inspect the application stack and do not expose environment values:

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

## Remaining production verification

The following items are not yet evidenced as completed and must be closed before declaring production readiness:

- Verify the actual runtime configuration and trusted-proxy settings on the VPS without printing `.env.production`, API keys, passwords, or `docker compose config` output that may interpolate secrets.
- Verify the host's externally exposed ports and confirm PostgreSQL/PHP-FPM are not publicly reachable. Do not change firewall rules or Docker networking without a separate reviewed plan because the host also runs legacy services.
- Confirm the exact Compose volume names and contents before writing backup commands for this host.
- Back up PostgreSQL and private application storage together, store backups outside the application container/volume, and protect them as sensitive data.
- Restore both backups into an isolated environment and verify database/storage consistency. A backup that has not been restored successfully is not an accepted recovery procedure.
- Complete a release procedure using immutable image tags, reviewed migrations, pre-deployment backups, and an explicit rollback plan.
- Add uptime/error monitoring and document alert ownership.

SMTP delivery and domain verification were confirmed by the owner during setup. Repeat a functional email-verification test after confirming the production queue worker is using the current configuration.

Do not run destructive QA, `migrate:fresh`, database resets, broad seeders, or volume deletion against production. Do not restart or modify unrelated legacy services as part of this work.
