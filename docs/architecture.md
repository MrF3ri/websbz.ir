# Architecture

## System shape

websbz.ir is a Laravel Modular Monolith using a shared database and shared schema. Business is the tenant boundary. Domain modules live under `app/Modules`; HTTP entry points use Form Requests, Policies, and thin controllers, with business operations in Actions and Services.

Microservices are explicitly out of scope.

## Identity and tenant ownership

- Internal primary keys are BIGINT.
- Public identifiers are ULIDs with unique database constraints.
- A public identifier is an opaque locator only; it is never an authorization credential.
- Tenant authorization is based on authenticated membership and server-side Policies.
- Client-supplied `business_id` and `menu_id` values never establish ownership.
- `CurrentBusiness` is registered as a scoped service, not a singleton.
- The active business is selected from the authenticated user's memberships. A valid session selection wins; otherwise the lowest internal business ID is selected deterministically.
- Invalid or foreign session selections are discarded and fall back to the user's own membership.
- A fresh request/job scope starts with an empty CurrentBusiness unless middleware or job code explicitly sets it.

## Persistence and time

- Money is stored as integer Toman.
- Database timestamps and API/Inertia values are Gregorian ISO 8601 UTC.
- User-facing dates are Jalali and converted only at the presentation boundary.
- Existing migrations are immutable; schema changes are additive migrations compatible with SQLite and PostgreSQL.

## Subscription and entitlements

- Plans define features; a subscription stores a feature snapshot so later plan edits do not silently rewrite an existing customer's contract.
- Numeric limit `-1` means unlimited. `null` means missing or unavailable, never unlimited.
- EntitlementService reads a subscription snapshot. EnforcementService is the single backend decision point.
- All quota enforcement is server-side and must be transactionally safe.

## Frontend

The dashboard uses Vue 3 Composition API, TypeScript, Inertia, Tailwind CSS 4, Wayfinder-generated routes, and Persian RTL presentation. Business logic remains on the server.

## Security boundaries

- Platform administration is distinct from BusinessRole.
- Public IDs do not replace authorization.
- Every tenant-owned query is scoped through CurrentBusiness and verified ownership.
- Private payment receipts use private storage and owner/admin-only access.
- Sensitive administrative actions are audited.
