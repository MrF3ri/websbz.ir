# Implementation Roadmap

This roadmap reflects the owner-approved execution order and current repository state as of 2026-11-15.

## Completed foundations

- Core Business and BusinessMember models, onboarding, tenant middleware, and scoped CurrentBusiness.
- Authentication baseline (Fortify, 2FA, passkeys).
- Plan, PlanFeature, Subscription, entitlement services, feature snapshots, and partial subscription lifecycle actions.

## Current staged delivery

### Stage B — Tenancy and test foundations
- Business and membership factories plus reusable test helpers.
- Add users.public_id as a unique, non-null ULID using a safe backfill migration.
- Expand slug-collision, transaction rollback, tenant isolation, deterministic active-business, and scoped-context tests.
- Keep public IDs out of Inertia props and never use them for authorization.
- Add architecture and decision records.

### Stage C — Onboarding trial, entitlements, and Jalali dates
- Plan selection during onboarding.
- Create business, owner membership, and 72-hour subscription trial in one transaction.
- Add an idempotent subscription:start-trial command for existing businesses.
- Share only safe current-tenant entitlement data through lazy Inertia props.
- Centralize Jalali display conversion in PHP and Vue; store and transmit Gregorian UTC.
- Add Vitest unit tests and show trial expiry on the dashboard.
- Replace hardcoded onboarding submission URL with Wayfinder.

### Stage D — Phase 8 menu and catalog
- One primary menu per business, categories, and products.
- Composite foreign key guarantees category/menu consistency.
- Tenant-scoped CRUD, authorization policies, sorting, search, and pagination.
- Enforce plan limits and feature flags on the server under a menu-row lock.
- Persian RTL administration UI.
- Images, public menu, branches, translations, QR codes, and other later roadmap phases remain out of scope.

## Subsequent product phases

- Phase 7 subscription lifecycle and billing completion (manual card-to-card provider first).
- Phase 9 branch management.
- Phase 10 media and product images.
- Phase 11 menu customization and templates.
- Phase 12 localization and translations.
- Phase 13 public menu.
- Phase 14 QR generation.
- Later phases cover Top-plan features, domains, security, operations, production deployment, and release management.

## Quality gates

Each stage is delivered on its own branch and PR. SQLite and PostgreSQL feature suites, PHPStan, Pint, vue-tsc, and frontend build are mandatory CI gates. Do not merge branches automatically.
