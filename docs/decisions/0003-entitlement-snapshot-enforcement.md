# ADR 0003: Versioned Entitlement Snapshot and Enforcement Split

## Context
Plan definitions may change, but existing subscriptions must not silently inherit those changes. Feature checks must be centralized at server-side write boundaries.

## Options
- Resolve every entitlement from the live Plan.
- Copy a feature snapshot to each subscription and version it.
- Scatter plan-name conditionals through controllers and UI.

## Chosen
Persist `features_snapshot` and plan version on subscription creation; replace explicitly on plan change. `EntitlementService` reads snapshot values; `EnforcementService` decides whether an operation is allowed. `-1` means unlimited; `null` means undefined/unavailable.

## Reasoning
A versioned snapshot stabilizes and audits subscription behavior. Separating resolution from enforcement distinguishes feature interpretation from operation authorization.

## Consequences
Usage counts are server-computed. Quota checks and writes are atomic and use stable-row pessimistic locking. Frontend visibility is not authorization. Template-count entitlements are deferred to Phase 11 (ADR 0006).
