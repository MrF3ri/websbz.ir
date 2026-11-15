# ADR 0004: Manual Card-to-Card as the First Payment Provider

## Context
The owner approved the first payment method on 2026-11-15. It must support renewals without assuming online gateway credentials.

## Options
- Integrate an online gateway first, requiring provider credentials and server-to-server verification.
- Accept manual card-to-card transfers, with owner-submitted receipt and human approval.
- Record off-system transfers without a controlled review workflow.

## Chosen
Implement a manual card-to-card provider behind `PaymentGatewayInterface`. Platform registered card details live in admin-editable platform settings and are never hardcoded. A Business owner transfers funds, submits a receipt, and supplies a unique bank tracking number. The single Platform Admin reviews and approves/rejects the request.

## Reasoning
This is the explicit approved first provider (ERRATA 7). For this provider, authenticated, audited admin approval is the trusted verification control; server-to-server verification is reserved for future online providers.

## Consequences
States are `pending_review`, `approved`, and `rejected`. Store receipts on private storage with strict validation. Only Platform Admin and owning Business may access a receipt. Enforce unique bank tracking numbers. Approval/rejection must be authenticated, recorded in `subscription_events`, idempotent, and executed under a DB row lock. Platform Admin is not a BusinessRole; initially it is the single owner, granted outside UI/registration and checked centrally on /admin routes. Online gateways may be added later.
