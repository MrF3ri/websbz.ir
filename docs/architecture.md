# Architecture

## System shape
websbz.ir is a Laravel **Modular Monolith**. Domain boundaries are modules in one deployable application; microservices are out of scope. Modules are introduced incrementally.

Planned modules (not all currently implemented): Identity, Business, Menu, Catalog, Branch, Media, Subscription, Billing, Domain, QRCode, PublicMenu, Review, Social, Notification, Backup.

## Tenancy
Use **Shared Database / Shared Schema**. Each Business is a tenant. Tenant-owned records must have a verifiable ownership path to one business. Internal primary keys are BIGINT; public identifiers are ULIDs with unique constraints. ULIDs are identifiers, never authorization credentials. Business slugs are globally unique.

The active tenant is represented by `CurrentBusiness`, registered with Laravel's scoped lifecycle. `TenantMiddleware` validates the authenticated user's membership before populating the context and persists the selected business in the session. Context must not leak across requests, jobs, or tests. Frontend-supplied `business_id` is untrusted; membership and authorization are checked server-side.

Catalog relations must use database composite foreign keys so product/category/menu relationships cannot cross business boundaries even if application validation is bypassed.

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
Payment providers implement `PaymentGatewayInterface`. The first approved provider is manual card-to-card: platform card details are editable platform settings, the business owner submits a receipt and unique bank tracking number, and the single Platform Admin reviews and approves/rejects it. Admin decisions are authenticated, audited in `subscription_events`, idempotent, and performed under a DB row lock. Receipts are private files accessible only to the Platform Admin and owning business. Online gateways may be added later.

Platform Admin is distinct from tenant `BusinessRole`. Initially there is one Platform Admin (the owner), provisioned outside registration/UI and enforced centrally for administrative routes.

## Dates and localization
Database storage, APIs, Inertia props, and date logic use Gregorian ISO 8601 UTC. Jalali is presentation-only in Asia/Tehran, converted at the edge through one central Vue helper and one central PHP helper using `intl`. Date inputs display a Jalali picker but submit Gregorian ISO values; server validation accepts Gregorian values only. Leap-year behavior is tested. See ADR 0008.

## Test strategy
The default fast suite uses SQLite in-memory. SQLite does not prove PostgreSQL row-locking or concurrency. A required PostgreSQL CI job runs the full PHPUnit suite. Locking/concurrency and composite-FK behavior must be tested against PostgreSQL as these features are implemented. Tenant IDOR tests cover direct HTTP access and major tenant-owned resources. Keep unit, feature, entitlement, payment lifecycle, and tenant-isolation coverage distinct.

## Current implementation boundary (2026-11-15)
Verified repository state includes Business/BusinessMember, scoped CurrentBusiness, TenantMiddleware, Fortify authentication (including 2FA/passkeys), Plan/PlanFeature/Subscription, EntitlementService/EnforcementService, and partial Phase 7 Actions/models. Billing routes/UI and the manual card-to-card workflow are not implemented. Catalog, menus, branches, media, public menu, QR, Docker/Compose, and this documentation foundation are not present on main at baseline. The repository is the source of implementation truth; the master spec is the source of approved requirements.
