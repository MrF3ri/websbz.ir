# ADR 0007: Manual Card-to-Card Payment Provider

- Status: Accepted
- Date: 2026-11-15

## Context
The first payment provider is manual card-to-card transfer. Online gateways may be added later.

## Decision
Implement the provider behind PaymentGatewayInterface. Platform card details are stored in editable platform settings, never hardcoded. A business owner uploads a receipt and unique bank tracking number to private storage. The single platform admin approves or rejects it. Payment states are pending_review, approved, and rejected. Approval/rejection is authenticated, audited in subscription_events, idempotent, and applied under a database row lock.

## Consequences
Server-to-server verification applies to online gateways. Manual payments use equivalent audited human approval. Receipt access is restricted to the platform admin and the owning business.
