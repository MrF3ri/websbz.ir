# Roadmap and delivery status

Last reviewed: 2026-11-15

This roadmap preserves the phase numbering in `docs/master-spec.md` and its ERRATA. Historical audit statements describe the repository at the time of that audit; the current status below reflects the changes merged afterward.

## Completed and merged

- **Baseline A:** Docker development topology, PostgreSQL CI, architecture documentation, and ADR foundation.
- **Baseline B:** tenant factories and isolation tests, `users.public_id` ULID, selected-plan onboarding, 72-hour trial, safe Inertia entitlement props, and Jalali presentation helpers.
- **Phase 7 — MVP manual billing path:** editable platform bank-card settings, private receipt upload, unique bank tracking number, single Platform Admin, authenticated approve/reject flow, payment-time plan feature snapshot, transactional subscription activation/renewal, idempotent review, and audit events.
- **Phase 8 — Menu and Catalog:** primary menu, category/product CRUD, sorting, search/filter/pagination, tenant authorization, entitlement limits, and same-menu composite foreign keys.
- **Phase 13 — Public Menu:** stable guest URL, publication/unpublication, and public visibility filters.
- **Phase 14 — QR:** local browser-side QR generation and downloadable SVG; no external QR service receives the menu URL.

The above work is merged to `main` through PRs #12, #16, #17, #18, #19, #20, and #21. Each feature PR passed the configured SQLite and PostgreSQL CI jobs before merge.

## Current release objective

The production build and deployment topology are now prepared and CI-validated (PR #22). The remaining work is owner-controlled provisioning and verification on a real server. A green pipeline and a production image build do not by themselves mean the product is deployed or production-ready.

Completed in the release-preparation layer:
- Production-only PHP 8.5 image with Node 22 asset build and no Composer dev dependencies.
- Production Compose topology with private PostgreSQL, Nginx, persistent private storage, queue worker, and scheduler.
- Explicit trusted reverse-proxy configuration, secure cookie defaults, and deployment/backup/rollback runbook.
- Production-image build and Compose configuration validation in CI.

Next owner-dependent step:
- Choose the VPS/hosting provider and region, connect the domain and DNS, supply SMTP details, provision TLS, and run the deployment checklist in `docs/deployment.md`.

## Deferred product phases

These are intentionally not blockers for the first usable MVP unless the business launch requires them:

- **Phase 9:** Branch management and mixed product settings.
- **Phase 10:** Media and product image management.
- **Phase 11:** Menu customization and visual templates; template-count entitlements start here.
- **Phase 12:** Localization and translation management.
- **Phase 15:** Top-plan advanced features (video, reviews/ratings, likes, Instagram API, Excel export, backup/restore, notifications/customer communication, initial setup service).
- **Phase 16:** Custom domains and HTTPS per tenant.
- **Phase 17:** Social links, contact, and location integrations.
- **Phase 18:** Expanded notifications, audit logs, and operational events.

## Remaining release gates

- **Phase 19 — QA:** run end-to-end acceptance tests on a clean PostgreSQL deployment, including payment receipt privacy and admin approval.
- **Phase 20 — Performance:** inspect catalog pagination/query plans and public menu cache behavior under representative load.
- **Phase 21 — Security:** complete dependency review, authorization review, upload validation review, rate limits, and production secret review.
- **Phase 22 — Production infrastructure:** validate image build and Compose config, provision host, DNS, TLS, SMTP, persistent volumes, and backups.
- **Phase 23 — CI/CD:** publish immutable images, deploy with reviewed migrations, and verify rollback procedure.
- **Phase 24 — Final readiness:** operational documentation, owner runbook, monitoring/alerts, restore drill, and launch acceptance.

Do not mark the product production-ready until the remaining release gates are completed on the actual deployment target.
