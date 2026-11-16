# Phase 19 — QA coverage notes

## Automated scenario added

`ManualPaymentRejectionFlowTest` exercises the HTTP path across the business and platform-admin boundaries:

1. A business owner submits a receipt and bank tracking number for a selected plan.
2. The payment is persisted as `pending_review` at the server-defined plan price.
3. A Platform Admin rejects the payment through the admin HTTP endpoint.
4. The payment becomes `rejected`, while the trial subscription status, plan, and period timestamps remain unchanged.
5. The business billing page shows the rejection result and its own receipt link.
6. The business owner can still download its own private receipt.
7. The platform audit row identifies the admin and payment target but excludes the bank tracking number, rejection reason, and receipt storage path.

The scenario runs under both configured database test jobs. PostgreSQL remains mandatory for concurrency and database-specific guarantees.

## Remaining QA boundaries

- This scenario does not replace the owner-controlled manual acceptance on a real deployment.
- QR code physical scan/print acceptance remains outstanding.
- Phase 19 still requires a broader clean-PostgreSQL deployment pass across authentication, tenant isolation, plan restrictions, public menu rendering, receipt privacy, and billing lifecycle scenarios.
- CI success proves the configured automated checks only; it does not prove production deployment, external integrations, email delivery, or physical QR scanning.