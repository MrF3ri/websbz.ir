> **STATUS: OUTDATED — NOT AN IMPLEMENTATION RECORD**
>
> This handoff document describes planned work, not verified repository functionality. Use [`docs/roadmap.md`](../roadmap.md) as the current execution order and [`docs/master-spec.md`](../master-spec.md) as approved requirements.
>
> The following claims in the original handoff are **not implemented** in the repository baseline: onboarding plan selection and automatic 72-hour trial start; complete subscription lifecycle (paid grace period, cancellation, renewal, expiration, upgrade/downgrade actions); manual card-to-card payment with receipt upload and Platform Admin approval; subscription access middleware and public-menu access policy; Jalali date conversion/presentation helpers; Billing/CRM routes and Vue pages; corresponding complete lifecycle/payment tests. Existing Phase 7 work is partial: Payment/SubscriptionEvent models, StartTrial/InitiatePayment/ProcessPaymentCallback actions, and related migrations/tests exist, but there are no billing routes/UI or manual-review workflow.
>
> The final paragraph below saying only a handoff document exists is also stale: partial Phase 7 source code has since been committed. This README is retained for historical context only.

# Phase 7 — Subscription Lifecycle & Billing

This branch was created from `main` at `f0032661a0936348d04081f827e8b9b41c86b774`.

The Phase 7 implementation patch was originally described as a downloadable archive in an accompanying ChatGPT response:
`websbz-phase7-implementation.tar.gz`.

## Patch contents (historical handoff claims)

- Business onboarding plan selection and 72-hour UTC trial creation
- Subscription lifecycle, paid grace period, cancellation, renewal, upgrade and downgrade actions
- Bank-transfer payment requests and CRM operator approval workflow
- Append-only subscription event history
- Subscription access middleware and public-menu access policy service
- Jalali date conversion and Persian date presentation
- Billing and CRM Vue pages, routes, migrations, tests and architecture notes

## Apply

The handoff instructions below are retained for historical context only. Do not apply the archive as a substitute for reviewing the current repository.

**Important:** This README is not an authoritative implementation status document. See `docs/roadmap.md`.
