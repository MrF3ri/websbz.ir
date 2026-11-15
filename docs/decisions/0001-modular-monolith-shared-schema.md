# ADR 0001: Modular Monolith and Shared-Schema Tenancy

## Context
The product is a multi-tenant QR menu SaaS built on Laravel. Domain responsibilities need boundaries without distributed-service operations.

## Options
- Modular Monolith with Shared Database / Shared Schema.
- Microservices with separate deployment and data boundaries.
- Separate database/schema per business.

## Chosen
A Modular Monolith using one shared database and shared schema. Every Business is a tenant.

## Reasoning
This matches the Laravel deployment model, keeps transactions straightforward, and supports incremental module delivery with strict tenant constraints.

## Consequences
Modules remain code-level boundaries in one application. Tenant-owned rows require verified business ownership. Cross-tenant access is denied server-side. No microservices or per-tenant databases without a new ADR.
