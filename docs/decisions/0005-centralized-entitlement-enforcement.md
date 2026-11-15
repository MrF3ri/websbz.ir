# ADR 0005: Centralized Entitlement Enforcement

- Status: Accepted
- Date: 2026-11-15

## Context
Plan limits and feature gates must be enforced consistently by the backend.

## Decision
EntitlementService is a read-only resolver for the active subscription snapshot. EnforcementService makes the authorization/limit decision. Controllers and Vue pages must not duplicate plan-code checks.

## Consequences
All create/update paths call the enforcement contract on the server. UI entitlement props are advisory only and never replace backend checks.
