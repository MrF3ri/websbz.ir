# Roadmap and delivery status

Last reviewed: 2026-11-25

This roadmap preserves the phase numbering in `docs/master-spec.md` and its ERRATA. Historical audit statements describe the repository at the time of that audit; the current status below reflects the changes merged afterward.

## Completed and merged

- **Baseline stabilization:** Docker development/production topology, PostgreSQL CI, BusinessFactory, tenancy IDOR coverage, `users.public_id`, selected-plan onboarding, 72-hour trial, safe Inertia entitlement props, Jalali presentation helpers, architecture documentation, and ADR foundation.
- **Phase 8 — Menu and Catalog:** primary menu, category/product CRUD, sorting, search/filter/pagination, tenant authorization, entitlement limits, and same-menu composite foreign keys.
- **Phase 13 — Public Menu:** stable guest URL, publication/unpublication, and public visibility filters.
- **Phase 14 — QR:** local browser-side QR generation and downloadable SVG; no external QR service receives the menu URL.
  - Owner's final physical scan/print acceptance is still outstanding; automated SVG generation tests do not replace scanning the downloaded QR with a phone.

The above work is merged to `main` through PRs #12, #16, #17, #18, #19, #20, and #21. Each feature PR passed the configured SQLite and PostgreSQL CI jobs before merge.

## Phase 7 — Subscription Lifecycle & Billing

The MVP manual card-to-card path is implemented on `main`:

- Plan selection during onboarding and a 72-hour trial with a persisted plan-feature snapshot.
- Platform card details managed through platform settings.
- Business payment submission with a private receipt, server-priced plan, and unique bank tracking number.
- Platform Admin approval/rejection, audit events, row locking, and idempotent review.
- Approval updates the subscription plan/snapshot and extends its paid period transactionally.
- A legacy gateway abstraction and callback processing Actions remain available for future online providers.

Automated Phase 7 lifecycle work is implemented and covered on `main`: persisted trial/paid expiration and scheduler (PR #29), delayed/concurrent manual review tests (PR #30), and concurrent gateway callback plus expiration/callback race tests (PR #31). The current audit also addresses uninterrupted entitlements during early payment/renewal; see `docs/phase-7/README.md`.

**Phase 7 — complete:** the owner has confirmed successful end-to-end manual card-to-card acceptance, including real receipt submission, Platform Admin review, approval/rejection, and verification of subscription access and dates. This owner-controlled acceptance is no longer a release blocker.

Merged supporting changes: PR #25 (tenant-scoped receipt access), PR #27 (catalog concurrency tests), PR #28 (plan-change event classification), PR #29 (persisted expiration), PR #30 (manual-payment concurrency), and PR #31 (gateway callback/expiration races).

See `docs/phase-7/README.md` for the implementation boundary and handoff notes.

## Current release objective

The application is already deployed on the owner's Ubuntu VPS. The initial production provisioning described in the original deployment checklist has been carried out; the remaining objective is to verify operational safety and establish a repeatable release process. A working website, successful SMTP test, green CI, and production image build do not by themselves establish production readiness.

### Production state recorded on 2026-11-17

- Domain: `websbz.ir` (with `www.websbz.ir`); HTTPS is served through Caddy, which reverse-proxies to the local Nginx web service.
- Application topology: Docker Compose production stack with Laravel/PHP-FPM, Nginx, PostgreSQL, queue worker, scheduler, and a one-shot asset-copy service.
- The web service is bound to loopback on host port `8080`; Caddy is the public TLS entry point.
- PostgreSQL is on the private Compose network; private application storage is persisted in a named volume.
- Production environment uses `APP_ENV=production`, `APP_DEBUG=false`, HTTPS URL and secure session-cookie settings.
- SMTP is configured through Resend using `smtp.resend.com`; domain verification and a successful delivery test were confirmed by the owner. No credentials or API keys belong in this repository.
- Initial production migrations and plan catalog seeding have been completed. Do not repeat first-deployment migrations/seeding as a routine deployment step.
- The existing legacy services on the same host are out of scope for this application's deployment. Do not stop, recreate, or reconfigure them as part of websbz release work.

This snapshot records the operational state reported and verified during the owner-assisted deployment session. It is not a substitute for a fresh host-side health check. Never commit `.env.production`, credentials, private keys, database dumps, or receipt files.

### Release-preparation layer already in place

- Production-only PHP 8.5 image with Node 22 asset build and no Composer development dependencies.
- Production Compose topology with private PostgreSQL, Nginx, persistent private storage, queue worker, and scheduler.
- Trusted reverse-proxy support and secure cookie defaults.
- Production-image build and Compose configuration validation in CI.

### Remaining owner-controlled release work

- Reconcile and verify the actual production runtime configuration without printing secret values.
- Perform a clean-PostgreSQL QA pass in a separate test environment; never use the live production database for destructive QA.
- Perform a real phone scan of a downloaded QR code and verify the printed-size result.
- Validate representative application load, queue/database connection behavior, image delivery, and slow-query visibility.
- Create and restore-test coordinated PostgreSQL and private-storage backups.
- Establish immutable image publishing, reviewed migrations, deployment, and rollback verification.
- Complete the operational runbook, monitoring/alerting, and final launch acceptance.

## Current product phase

- **Phase 12 — Localization and Translation Management:** in progress. PRs #83–#87 delivered the locale registry/schema, Top-only translation management, public-menu locale rendering, translation completeness, and tenant/cache regression coverage. Remaining: decide whether banner and business-introduction fields enter the approved translation model, then complete the repository-wide Release Gate. Only Top supports `fa`, `en`, and `ar`; Economic and Professional remain single-language. See `docs/phase-12/README.md`.
- **Phase 9 — Branch Management:** implementation resumed. The branch schema/lifecycle foundation is already merged; increment 9.1 centralizes canonical-to-branch product value resolution. The public branch read path, branch catalog settings UI, branch-specific URLs/QR, and PostgreSQL concurrency acceptance remain. See `docs/phase-9/README.md`.
- **Phase 11 — Menu Customization and Visual Templates:** complete and merged through PR #82. See `docs/phase-11/README.md` for increment-level implementation and CI history.

## Deferred product phases

These are intentionally separate from the current localization increment:

- **Phase 10:** Media and product image management.
- **Phase 15:** Top-plan advanced features (video, reviews/ratings, likes, Instagram API, Excel export, backup/restore, notifications/customer communication, initial setup service).
- **Phase 16:** Custom domains and HTTPS per tenant.
- **Phase 17:** Social links, contact, and location integrations.
- **Phase 18:** Expanded notifications, audit logs, and operational events.

## Platform Admin dashboard

- Existing admin capabilities: manual payment review and platform card settings.
- PR #39 adds the first real `/admin` overview with live business, active/trial subscription, pending payment, approved payment totals, and recent payment data. PR #40 adds a searchable and filterable paginated business directory with each business's latest subscription summary; PR #41 adds the subscription directory; PR #42 adds payment history and review filters.
- PR #43 adds a read-only business detail view: contact and owner information, members and roles, latest subscription, ten recent payments, and ten recent subscription events. Receipt access uses the existing protected receipt endpoint; private storage paths and raw event metadata are not exposed.
- PR #44 adds the first operational event center at `/admin/events`, with filtering by lifecycle event type and business/plan search, newest-first pagination, links to business details, and a deliberately restricted event projection. It exposes only the existing subscription lifecycle and manual-payment events.
- PR #45 adds `/admin/audit-logs` and transactionally records platform card-setting changes with actor, target, timestamp, and safe before/after snapshots. Card numbers are reduced to their final four digits; raw card numbers, receipt paths, and arbitrary request payloads are excluded. A follow-up migration enforces append-only audit rows with PostgreSQL and SQLite database triggers, including protection against query-builder UPDATE/DELETE statements.
- PR #46 extends the audit trail to manual payment approval and rejection. It records actor, payment target, status, amount, plan, and subscription-period transitions in the same transaction as the review. Rejection reasons, bank tracking numbers, receipt paths, and feature snapshots are excluded from audit snapshots.
- The current branch also records the one-time `platform-admin:grant` CLI operation, with an explicit `CLI / Artisan` actor label, target public ULID, and before/after privilege flag. Repeated grants do not create duplicate records.
- The current branch adds a platform configuration-health section to `/admin`, covering completeness of the manual payment destination, availability of active plans, separation of private receipt storage from public storage, and a non-synchronous queue backend. It reports configuration only; it does not claim to verify worker liveness, external integrations, or email delivery, and it never exposes secret values.

## Remaining release gates

- **Phase 19 — QA:** run end-to-end acceptance tests on a clean PostgreSQL deployment, including payment receipt privacy and admin approval. The current branch adds HTTP-level submission-to-admin-rejection and submission-to-admin-approval scenarios. They verify that rejection preserves the trial subscription and period; approval activates the server-priced selected plan while preserving the trial period and extending it by one paid month; receipts remain tenant-private and admin-accessible; and audit snapshots exclude tracking numbers, rejection reasons, and receipt paths. These are covered billing scenarios, not completion of Phase 19.
- **Phase 20 — Performance:** PostgreSQL query-plan benchmark and explicit menu-scope optimization are merged (PR #35). Public-menu response caching with write-path invalidation is merged (PR #36). Billing history has a bounded query budget (PR #37). Catalog substring-search plan measurement is merged (PR #38); the observed CI sample did not justify adding a trigram index. The current branch adds a bounded SELECT-query regression test for the Platform Admin dashboard at 1 versus 31 payments. Representative-load validation, image delivery, queue/connection review, and slow-query monitoring remain.
- **Phase 21 — Security:** baseline HTTP security headers are merged, and this follow-up aligns production Nginx static/dynamic responses with the Laravel policy, prevents duplicate upstream headers, and adds `nginx -t` to CI. CSP is deliberately deferred until a nonce/hash and asset-origin policy is designed. Production and development dependency auditing is added on the current branch (`composer audit --locked` and high/critical `npm audit` across all dependency groups). PR #56 hardens manual receipt validation by independently checking detected MIME and filename extension, retaining the 5 MiB limit, and testing that rejected files are never stored. PR #57 adds regression coverage for the five-per-minute two-factor challenge limit and the 60-second per-user password-reset throttle. PR #58 defaults production session cookies to Secure and verifies Secure/HttpOnly/SameSite flags. The current review found no tenant-boundary bypass in the reviewed routes and records the repository secret-handling review. Remaining owner-controlled work: historical secret scanning and verification of actual production runtime secrets/host configuration; the connected source review cannot certify deployed credentials.
- **Phase 22 — Production infrastructure:** host, DNS, TLS, SMTP, Compose services, PostgreSQL, and persistent application storage are provisioned on the owner's VPS. Remaining: verify runtime configuration/host exposure, implement coordinated database and private-storage backups, and complete a restore drill. The prior first-provisioning checklist is historical; see the current-state warning in `docs/deployment.md`.
- **Phase 23 — CI/CD:** CI and production-image build validation exist. Remaining: publish immutable images, deploy a reviewed release with reviewed migrations, and verify rollback procedure.
- **Phase 24 — Final readiness:** reconcile operational documentation, complete owner runbook, monitoring/alerts, restore drill, and launch acceptance.

Do not mark the product production-ready until the remaining release gates are completed on the actual deployment target.
