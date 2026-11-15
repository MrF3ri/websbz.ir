# ADR 0002: Shared Database and Shared Schema Tenancy

- Status: Accepted
- Date: 2026-11-15

## Context
Each business is a tenant and the product must support multiple businesses per user.

## Decision
Use one shared database/schema. Tenant-owned data is related to a business directly or through a verified ownership chain. Internal IDs are BIGINT; public identifiers are unique ULIDs and are never authorization credentials.

## Consequences
Every tenant query and write must be scoped and authorized server-side. Composite foreign keys are preferred where they can enforce same-tenant relationships.
