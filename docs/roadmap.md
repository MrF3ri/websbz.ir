# Roadmap and delivery status

Last reviewed: 2026-11-16

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

**Phase 7 remains open only for owner-controlled end-to-end acceptance of manual card-to-card billing.** The owner must submit a real receipt, review it as Platform Admin, exercise approval and rejection, and verify the resulting subscription access, dates, and private receipt boundaries. Do not mark Phase 7 complete before this acceptance passes.

Merged supporting changes: PR #25 (tenant-scoped receipt access), PR #27 (catalog concurrency tests), PR #28 (plan-change event classification), PR #29 (persisted expiration), PR #30 (manual-payment concurrency), and PR #31 (gateway callback/expiration races).

See `docs/phase-7/README.md` for the implementation boundary and handoff notes.

## Current release objective

The production build and deployment topology are prepared and CI-validated (PR #22). The remaining work is owner-controlled provisioning and verification on a real server. A green pipeline and a production image build do not by themselves mean the product is deployed or production-ready.

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

## Platform Admin dashboard

- Existing admin capabilities: manual payment review and platform card settings.
- PR #39 adds the first real `/admin` overview with live business, active/trial subscription, pending payment, approved payment totals, and recent payment data. PR #40 adds a searchable and filterable paginated business directory with each business's latest subscription summary; PR #41 adds the subscription directory; PR #42 adds payment history and review filters.
- PR #43 adds a read-only business detail view: contact and owner information, members and roles, latest subscription, ten recent payments, and ten recent subscription events. Receipt access uses the existing protected receipt endpoint; private storage paths and raw event metadata are not exposed.
- PR #44 adds the first operational event center at `/admin/events`, with filtering by lifecycle event type and business/plan search, newest-first pagination, links to business details, and a deliberately restricted event projection. It exposes only the existing subscription lifecycle and manual-payment events.
- PR #45 adds `/admin/audit-logs` and transactionally records platform card-setting changes with actor, target, timestamp, and safe before/after snapshots. Card numbers are reduced to their final four digits; raw card numbers, receipt paths, and arbitrary request payloads are excluded. The log has no application update/delete workflow.
- The current branch extends the audit trail to manual payment approval and rejection. It records actor, payment target, status, amount, plan, and subscription-period transitions in the same transaction as the review. Rejection reasons, bank tracking numbers, receipt paths, and feature snapshots are excluded from audit snapshots.
- Remaining dashboard slices: extend audited coverage to other sensitive administrative actions through explicit per-action snapshot allowlists, then add platform configuration health; each gated by authorization and regression tests.

## Remaining release gates

- **Phase 19 — QA:** run end-to-end acceptance tests on a clean PostgreSQL deployment, including payment receipt privacy and admin approval.
- **Phase 20 — Performance:** PostgreSQL query-plan benchmark and explicit menu-scope optimization are merged (PR #35). Public-menu response caching with write-path invalidation is merged (PR #36). Billing history has a bounded query budget (PR #37). Catalog substring-search plan measurement is merged (PR #38); the observed CI sample did not justify adding a trigram index. Representative-load validation, image delivery, queue/connection review, and slow-query monitoring remain.
- **Phase 21 — Security:** complete dependency review, authorization review, upload validation review, rate limits, and production secret review.
- **Phase 22 — Production infrastructure:** validate image build and Compose config, provision host, DNS, TLS, SMTP, persistent volumes, and backups.
- **Phase 23 — CI/CD:** publish immutable images, deploy with reviewed migrations, and verify rollback procedure.
- **Phase 24 — Final readiness:** operational documentation, owner runbook, monitoring/alerts, restore drill, and launch acceptance.

Do not mark the product production-ready until the remaining release gates are completed on the actual deployment target.
