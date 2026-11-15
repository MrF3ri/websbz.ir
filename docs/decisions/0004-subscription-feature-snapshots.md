# ADR 0004: Subscription Feature Snapshots and Unlimited Limits

- Status: Accepted
- Date: 2026-11-15

## Context
Plan definitions can change after a customer subscribes. Existing subscriptions must retain the features purchased at the time of activation.

## Decision
Persist the effective feature map on the subscription with a plan version marker. EntitlementService reads the snapshot. The numeric value -1 means unlimited; null means undefined or unavailable and must not be interpreted as unlimited.

## Consequences
Plan changes require an explicit subscription transition to refresh the snapshot. Tests must cover snapshot stability and every consumer must use the shared entitlement contract.
