# ADR 0002: Session-Based Scoped CurrentBusiness

## Context
A user may belong to multiple businesses. Requests need a validated active tenant, while workers and tests must not retain tenant state.

## Options
- Session-selected tenant context.
- Tenant inferred from hostname on every request.
- Global singleton context.
- Pass business identifiers through every application call.

## Chosen
Use a dedicated `CurrentBusiness` service registered with Laravel's scoped lifecycle. TenantMiddleware validates membership, resolves the active business, and persists the selection in the session.

## Reasoning
The session fits the authenticated dashboard. Scoped lifecycle prevents state leaking across request/job lifecycles; server-side membership checks prevent forged context from authorizing access.

## Consequences
Never register CurrentBusiness as a singleton. Initialize it per scope. Validate membership before setting it, handle stale sessions safely, and do not trust frontend `business_id` or ULIDs for authorization.
