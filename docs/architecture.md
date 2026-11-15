# Architecture

## System shape

websbz.ir is a Laravel **Modular Monolith**. Domain boundaries are modules in one deployable application; microservices are out of scope. Modules are introduced incrementally.

Implemented modules include Identity, Business, Tenancy, Menu, Category, Product, Catalog, PublicMenu, Subscription, and Platform administration. Branch, Media, Domain, Review, Social, Notification, and Backup capabilities remain deferred until their approved phases.

## Tenancy

Use **Shared Database / Shared Schema**. Each Business is a tenant. Tenant-owned records must have a verifiable ownership path to one business. Internal primary keys are BIGINT; public identifiers are ULIDs with unique constraints. ULIDs are identifiers, never authorization credentials. Business slugs are globally unique.

The active tenant is represented by `CurrentBusiness`, registered with Laravel's scoped lifecycle. `TenantMiddleware` validates the authenticated user's membership before populating the context and persists the selected business in the session. Context must not leak across requests, jobs, or tests. Frontend-supplied `business_id` is untrusted; membership and authorization are checked server-side.

Catalog relations use database composite foreign keys so product/category/menu relationships cannot cross business boundaries even if application validation is bypassed.

## Backend boundaries

Use Form Requests for validation, Policies for authorization, and Actions/Services for business operations. Controllers coordinate HTTP concerns; Vue components do not own domain rules. Multi-step writes use transactions. Use enums for stable states and queues only where justified. Avoid unnecessary repositories, global scopes, abstractions, and duplicated authorization logic.

## Entitlements and enforcement

The Subscription module owns plans, plan features, subscriptions, and entitlement snapshots.

- `EntitlementService` reads persisted `features_snapshot` values/limits; it does not decide whether a particular write is authorized.
- `EnforcementService` makes allow/deny decisions for feature and quota operations.
- `-1` means unlimited; `null` means undefined/unavailable, not unlimited.
- A subscription captures a versioned snapshot. Later edits to a live plan do not silently mutate existing subscription entitlements; a plan change explicitly replaces the snapshot and version.

Quota counts are computed server-side. Quota checks and writes must be atomic and protected by a stable row lock/transaction as defined by the entitlement enforcement contract. Hiding a frontend control is not authorization. Template-count entitlements are deferred to Phase 11 (ADR 0006).

## Billing boundary

The approved first provider is **manual card-to-card**, implemented alongside the payment gateway abstraction. Platform card details are editable platform settings, never hardcoded. The business owner submits a receipt and unique bank tracking number; the single Platform Admin reviews and approves/rejects it. Admin decisions are authenticated, audited in `subscription_events`, idempotent, and performed under a DB row lock. Receipts are stored on the private local disk, not the public disk.

As of the current `main` baseline, both the Platform Admin and the owning business have private receipt-download routes. The business-owner route verifies payment ownership through the subscription and current business context; PR #25 adds a cross-tenant IDOR regression test.

Online gateway initiation/callback Actions remain in the code behind `PaymentGatewayInterface`; they are not a substitute for the approved manual provider and must not be treated as a production-connected online gateway.

Platform Admin is distinct from tenant `BusinessRole`. Initially there is one Platform Admin (the owner), provisioned outside registration/UI and enforced centrally for administrative routes.

## Dates and localization

Database storage, APIs, Inertia props, and date logic use Gregorian ISO 8601 UTC. Jalali is presentation-only in Asia/Tehran, converted at the edge through the central Vue helper and central PHP helper using `intl`. Date inputs must submit Gregorian ISO values; server validation accepts Gregorian values only. Leap-year behavior is covered by regression tests. See ADR 0008.

## Test strategy

The default fast suite uses SQLite in-memory. SQLite does not prove PostgreSQL row-locking or concurrency. A required PostgreSQL CI job runs the full PHPUnit suite. Locking/concurrency and composite-FK behavior must be tested against PostgreSQL. PR #27 adds deterministic multi-process PostgreSQL tests for simultaneous product/category quota writes, rollback behavior, and independent-business locks. Tenant IDOR tests cover direct HTTP access and major tenant-owned resources. Keep unit, feature, entitlement, payment lifecycle, and tenant-isolation coverage distinct.

Current CI includes SQLite quality/test checks, a PostgreSQL test job, and production image/Compose validation. Green CI does not prove a live deployment or production readiness.

## Current implementation boundary (2026-11-15)

Verified repository implementation includes:

- Docker development and production Compose configurations, PHP 8.5 images, Nginx, PostgreSQL, and CI validation.
- Business/BusinessMember, `BusinessFactory`, scoped `CurrentBusiness`, `TenantMiddleware`, membership authorization, and tenancy IDOR tests.
- `users.public_id` ULID with mass-assignment protection; it is not used as an authorization credential.
- Plan/PlanFeature/Subscription, selected-plan onboarding, exact 72-hour trial creation, versioned feature snapshots, `EntitlementService`, and `EnforcementService`.
- Inertia shared entitlement props with sensitive user fields excluded.
- Menu, Category, Product and Catalog CRUD, sorting, search/filter/pagination, tenant authorization, entitlement limits, and same-menu composite foreign keys.
- Public menu publication/unpublication, guest rendering, and browser-side downloadable QR generation.
- Manual card-to-card billing: editable platform card settings, private receipt upload, unique bank tracking number, Platform Admin review, approval/rejection, transactional subscription renewal/plan snapshot update, idempotency, row locks, and subscription event history.

Known remaining work is tracked in `docs/roadmap.md` and `docs/phase-7/README.md`. Phase 7 still needs explicit persisted expiration transition/access behavior and PostgreSQL coverage for delayed/concurrent payment review and renewal boundaries, plus owner end-to-end acceptance of manual billing. PR #28 classifies plan changes as upgraded, downgraded, or renewed; PR #25 completes business-owner receipt access; PR #27 adds PostgreSQL catalog quota-concurrency coverage. Cancellation and paid grace periods are not defined as Phase 7 requirements in the approved specification.

The repository has not been deployed to a real VPS. DNS, TLS, SMTP, production secrets, persistent-volume backup/restore, deployment, rollback, monitoring, and live acceptance remain owner-controlled release work.
