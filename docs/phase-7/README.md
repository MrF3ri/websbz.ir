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

## Pending change

PR #25 (`fix/phase-7-business-receipt-access`) adds the authenticated business-side receipt download route, verifies the payment belongs to the current business through its subscription, exposes download links in billing history, and adds a cross-tenant receipt-access regression test.

Until PR #25 is merged, do not claim that business owners can download their own uploaded receipts from the application. The Platform Admin receipt download is already present.

## Remaining Phase 7 work

Review the complete Phase 7 acceptance criteria in `docs/master-spec.md` and close the gaps deliberately:

1. Define and test the exact expired-subscription transition and access behavior after trial/paid period end. The entitlement resolver currently denies access when the period is over; that alone is not a complete persisted lifecycle transition.
2. Implement the Phase 7 downgrade behavior with explicit feature-snapshot and effective-time semantics, plus state-transition and event-history tests. The approved Phase 7 task list does not define cancellation or a paid grace period; do not invent either as a requirement without a separate owner decision.
3. Add PostgreSQL integration coverage for concurrent/delayed payment review and renewal edge cases; SQLite does not prove row-lock semantics.
4. Verify the manual provider end-to-end through owner acceptance: submit receipt, review as Platform Admin, approve/reject, and confirm subscription access and period.
5. Keep online gateway callbacks separate from the manual provider. Do not add or assume real gateway credentials.

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
