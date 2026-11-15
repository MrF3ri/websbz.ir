# ADR 0005: Composite Foreign Keys for Catalog Tenant Integrity

## Context
Catalog entities are tenant-owned. Application validation alone cannot prevent every invalid cross-business relationship.

## Options
- Validate same-menu relationships only in application code.
- Add composite unique keys and composite foreign keys including tenant/business ownership.
- Use separate schemas/databases per tenant.

## Chosen
Use database-level composite foreign keys for catalog tenant integrity. Define referenced composite unique constraints and ensure each relation carries and matches the relevant business/menu ownership key.

## Reasoning
The database becomes a final integrity boundary against cross-tenant references, including writes outside HTTP controllers.

## Consequences
Catalog schema design and migration ordering must account for composite constraints. PostgreSQL integration tests are required. Do not weaken constraints for SQLite convenience; PostgreSQL validates production semantics.
