# Phase 7 — Subscription Lifecycle & Billing

## Current status

**MVP manual card-to-card billing is implemented; Phase 7 is not yet fully complete.** This document is the current implementation record, not a historical handoff archive. The approved project requirements are in the ERRATA at the top of `docs/master-spec.md`.

## Implemented on `main`

- Onboarding requires a selected active plan; the server resolves the plan and creates the subscription.
- `StartTrialAction` serializes trial creation per business, captures the persisted plan-feature snapshot, records a trial event, and sets the trial end to exactly 72 hours after start.
- `EntitlementService` reads the subscription snapshot; `EnforcementService` makes feature/quota decisions.
- Platform card details are stored in platform settings and edited through the Platform Admin area; card details are not hardcoded.
- The business owner can submit a payment against an active server-selected plan, with a private receipt upload and a unique bank tracking number.
- Receipt files are stored on the private `local` disk, not the public disk.
- `SubmitManualPaymentAction` locks the subscription, prevents a second pending review, snapshots the target plan features and price, creates the `pending_review` payment, and records a subscription event.
- `ReviewManualPaymentAction` locks the payment and subscription, handles approve/reject idempotently, records reviewer/review timestamps and event history, and on approval updates the plan snapshot and paid period in the same transaction.
- Platform Admin payment review and receipt download routes/UI exist.
- The gateway abstraction and legacy online-payment initiation/callback Actions remain in the code; the approved first provider is still manual card-to-card.
- Feature tests cover 72-hour trial creation, plan snapshots, gateway callback behavior, manual payment submission, duplicate bank tracking number rejection, approval, rejection, and idempotency.

## Recently completed changes

- PR #25 (`fix/phase-7-business-receipt-access`) is merged. Business owners can download receipts from billing history through an authenticated tenant-scoped route; ownership is verified through the payment's subscription and current business. A cross-tenant receipt-access regression test covers denial of another business's receipt.
- PR #27 adds deterministic PostgreSQL multi-process concurrency tests for catalog quota enforcement, rollback, and independent-business locking. These tests do not cover payment lifecycle races.
- PR #28 (`fix/phase-7-lifecycle-completion`) is merged. Manual approval and verified gateway callback paths classify same-plan periods as `renewed`, lower-priced target plans as `downgraded`, and higher-priced target plans as `upgraded`, with downgrade regression tests.
- PR #29 persists trial and paid-period expiration transitions, emits idempotent lifecycle events, and schedules the expiration command.
- PR #30 adds PostgreSQL multi-process tests for delayed and concurrent manual-payment review.
- PR #31 adds PostgreSQL multi-process tests for concurrent successful callbacks and the expiration/callback row-lock race.
- The current renewal-access audit adds coverage for early manual and gateway renewals so an approved payment does not interrupt an unexpired trial or paid period.

## Remaining Phase 7 work

Automated lifecycle implementation and PostgreSQL race coverage are in place. The remaining Phase 7 release gate is owner-controlled end-to-end acceptance of the approved manual provider:

1. Configure the platform card details as Platform Admin.
2. Submit a real transfer receipt from a Business owner account and confirm it appears as `pending_review`.
3. Review the private receipt as Platform Admin; exercise both rejection and approval using separate payment submissions.
4. Confirm rejection leaves the subscription and entitlements unchanged.
5. Confirm approval activates the selected plan, preserves uninterrupted access, records the reviewer/event, and extends the paid period exactly as shown in the billing UI.
6. Confirm the Business owner can download only their own receipt and cannot access another Business's receipt.

Keep online gateway callbacks separate from the approved manual provider. Do not add or assume real gateway credentials. Do not mark Phase 7 complete until the owner acceptance above has passed.

## Important invariants

- Trial duration is exactly 72 hours for all three plans.
- Prices and plan feature snapshots are resolved server-side; never trust submitted amounts or feature values.
- Payment states for the manual provider are `pending_review`, `approved`, and `rejected`.
- Bank tracking numbers are unique.
- Receipt files remain private. Only the Platform Admin and the owning Business may access them.
- Approval/rejection is authenticated, audited, idempotent, and performed under database row locks.
- Subscription changes and payment finalization are transactional.
- Dates are stored and transported as Gregorian ISO 8601 UTC; only presentation is Jalali in Asia/Tehran.

## Verification

The repository CI runs SQLite quality/test checks, the full PostgreSQL test suite, and production image/Compose validation. Use PostgreSQL for concurrency, row-lock, and database-constraint verification. A green workflow does not prove a live production deployment.
