# Roadmap

This roadmap follows ERRATA dated 2026-11-15. Phase numbering is unchanged: Phase 7 is Subscription Lifecycle & Billing; Phase 8 is Menu & Catalog.

## Baseline-A — Documentation, Docker, PostgreSQL CI
- Publish the verbatim master specification including ERRATA.
- Establish architecture documentation and ADRs 0001–0008.
- Add a reproducible PHP 8.5 + PostgreSQL Docker development environment.
- Retain fast SQLite CI and add a full PostgreSQL PHPUnit job.
- Run PHPStan with a 1G memory limit; retain Pint, vue-tsc, and Vite build checks.
- Mark the old Phase 7 handoff README outdated and link this roadmap.

## Baseline-B — Stabilization before feature phases
- Add `BusinessFactory`.
- Add cross-tenant IDOR tests for tenant-owned routes and resources.
- Add `users.public_id` as a ULID (never an authorization mechanism).
- Share safe entitlement information with Vue through Inertia shared props.
- Add Jalali date helpers + tests. Keep Gregorian ISO 8601 UTC in storage, APIs, Inertia props, and logic; Jalali conversion belongs only at the presentation edge (ADR 0008).
- Onboarding starts a 72h trial on the selected plan (ERRATA 11).
- Replace hardcoded `form.post('/onboarding')` with the generated route helper.
- Replace dashboard placeholder statistics with real data when corresponding domain data exists.

## Phase 8 — Menu and Catalog
Implement Menu and Catalog modules incrementally: one primary menu per business, categories/products, stable sorting, validation, integer Toman amounts, publication status, pagination/search where appropriate, and backend entitlement enforcement. Enforce same-menu relationships and tenant integrity with composite foreign keys (ADR 0005). Add tenant isolation and CRUD tests. Do not implement branches, media, public menu, or QR ahead of their scheduled phases.

## Finish Phase 7 — Subscription Lifecycle & Billing
After Baseline stabilization and Phase 8, complete the existing partial Phase 7 implementation:
- Trial creation is exactly 72 hours on the plan selected during onboarding, with a snapshot of that plan.
- Complete activation, monthly period calculation, renewal, expiration, upgrade/downgrade, and post-expiration behavior without deleting customer data.
- Implement manual card-to-card behind `PaymentGatewayInterface`: platform card details in admin-editable settings; owner uploads receipt and unique bank tracking number; receipt stored privately; only owning business and Platform Admin can access it.
- Platform Admin (single owner, not a BusinessRole) reviews and approves/rejects payments. Approval is authenticated, audited in `subscription_events`, idempotent, and protected by a DB row lock. States: `pending_review`, `approved`, `rejected`.
- Keep online gateways as future providers; server-to-server verification applies to online gateways, while manual approval is the trusted control for the manual provider.
- Add routes, controllers, validation, UI, access policies, scheduling, and comprehensive tests in Phase 7 work.

## Later phases
- Phase 9 — Branch Management and Mixed Product Settings.
- Phase 10 — Media and Product Image Management.
- Phase 11 — Menu Customization and Visual Templates (template-count entitlements are introduced here, not earlier).
- Phase 12 — Localization and Translation Management.
- Phase 13 — Public Menu and Customer Experience.
- Phase 14 — QR Code Generation and URL Management.
- Phase 15 — Top Plan Advanced Features (video, reviews/ratings, likes, Instagram API integration, Excel export, backup/restore, notifications/customer communication, initial setup service).
- Phase 16 — Custom Domains and HTTPS.
- Phase 17 — Social Links, Contact and Location.
- Phase 18 — Notifications, Audit Logs and Operational Events.
- Phase 19 — Testing and Quality Assurance.
- Phase 20 — Performance and Scalability.
- Phase 21 — Security Hardening.
- Phase 22 — Production Infrastructure and Deployment.
- Phase 23 — CI/CD and Release Management.
- Phase 24 — Documentation and Final Product Readiness.

Do not label the product production-ready until critical acceptance criteria are met.
