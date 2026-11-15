# ADR 0006: Persist Payment Target Plan

- Status: Accepted
- Date: 2026-11-15

## Context
A pending payment may be initiated for a plan different from the subscription's current plan. The target must remain stable if plan configuration changes before approval.

## Decision
Persist payments.target_plan_id with a foreign key to plans. The amount and target plan are captured when the payment is initiated; callback processing resolves the persisted target plan rather than trusting browser input.

## Consequences
Payment approval is idempotent and updates the subscription snapshot from the persisted target plan.
