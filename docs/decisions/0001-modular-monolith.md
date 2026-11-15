# ADR 0001: Modular Monolith

- Status: Accepted
- Date: 2026-11-15

## Context
The product is a single Laravel SaaS with several domain areas and a small initial team.

## Decision
Use a Modular Monolith. Keep domain code under `app/Modules` and deploy one Laravel application. Do not introduce microservices.

## Consequences
Domain boundaries remain explicit without distributed transactions, deployment overhead, or service-to-service authentication.
