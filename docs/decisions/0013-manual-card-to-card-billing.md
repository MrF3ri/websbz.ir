# ADR 0013: Manual card-to-card subscription payments

## Status

Accepted

## Context

The MVP will not integrate an online payment gateway. Subscription payments are transferred to a platform-owned bank card and reviewed by the single platform administrator.

## Decision

- The platform administrator's bank name, 16-digit card number, and card-holder name are editable only through protected platform settings.
- A business submits the target plan, a unique bank tracking number, and a receipt file (JPEG, PNG, or PDF, up to 5 MB).
- Receipts are stored on the private local disk and are never exposed through public storage URLs.
- Payment amounts are read from the active plan record on the server; client-provided amounts are ignored.
- The selected plan's feature snapshot is captured when the payment is submitted and is the snapshot granted after approval, even if the plan definition changes while review is pending.
- New manual payments use `pending_review`, `approved`, and `rejected` states.
- The bank tracking number has a database unique constraint.
- The subscription row is locked when a payment is submitted and when an administrator reviews it.
- Approval updates payment status, selected plan, feature snapshot, subscription period, and audit event in one transaction.
- Rejection leaves the subscription unchanged and records the administrator and reason.
- Repeated approval/rejection requests are idempotent for the already-applied decision.
- Exactly one platform administrator is granted through an Artisan command. The flag is not mass assignable and is not a business membership role.

## Consequences

- No gateway credentials or callback endpoints are required for the MVP.
- Payment review is auditable and can be completed from the platform admin area.
- Receipt access is restricted to the platform administrator.
- Automated gateway payments can be introduced later behind a separate payment workflow.
