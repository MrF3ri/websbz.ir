# Phase 19 — QA coverage and acceptance matrix

Last reviewed: 2026-11-17.

This document separates existing automated coverage from owner-controlled acceptance that must be performed against an isolated staging deployment. A green CI run is evidence only for the jobs and tests that actually ran; it is not a production acceptance certificate.

## Test environment and safety rules

- The GitHub Actions workflow `.github/workflows/tests.yml` runs a SQLite test suite and a separate PostgreSQL 17 test suite.
- The PostgreSQL job creates an ephemeral `websbz_test` database, verifies the connection with `php artisan db:show`, and runs `php artisan test`. Tests using `RefreshDatabase` or `DatabaseMigrations` create and roll back schema/data as part of the test lifecycle.
- PostgreSQL-only concurrency tests explicitly skip when the active driver is not `pgsql`.
- For manual acceptance, use a disposable staging deployment and a fresh PostgreSQL database with no production data. Never point `APP_ENV=testing`, `php artisan test`, `migrate:fresh`, or a test runner at the live production database.
- Use synthetic users, businesses, receipts, and bank references. Do not copy production receipts or credentials into test fixtures.
- Keep outbound mail/payment integrations disabled or pointed at controlled test endpoints during automated QA. Real email delivery is a separate owner-controlled smoke test.

## Existing automated coverage

The following test files exist on `main`; their presence does not imply that a new run was performed as part of this documentation change.

| Area | Existing test coverage |
|---|---|
| Authentication | `AuthenticationFlowTest`, `AuthenticationTest`, `RegistrationTest`, `EmailVerificationTest`, `PasswordResetTest`, `TwoFactorChallengeTest` |
| Business onboarding/settings | `BusinessOnboardingTest`, `CreateBusinessActionTest`, `OnboardingTrialTest`, `BusinessSettingsTest` |
| Tenant isolation | `TenantIsolationContractTest`, `TenantMiddlewareTest`, `CatalogTenantIsolationTest` |
| Catalog and plan limits | `CatalogCrudTest`, `CatalogEntitlementTest`, `PlanRestrictionTest`, `EntitlementServiceTest`, `CatalogCompositeForeignKeyTest` |
| PostgreSQL concurrency | `CatalogPostgresConcurrencyTest`, `SubscriptionPaymentPostgresConcurrencyTest` |
| Menu and public rendering | `MenuPublicationTest`, `PublicMenuTest` |
| Subscription lifecycle and billing | `SubscriptionLifecycleTest`, `ManualPaymentSubmissionTest`, `ManualPaymentReviewTest`, `ManualPaymentApprovalFlowTest`, `ManualPaymentRejectionFlowTest`, `SubscriptionPlanChangeEventTest` |
| Platform administration and audit | `PlatformAdminAccessTest`, `AdminDashboardTest`, `AdminBusinessDirectoryTest`, `AdminSubscriptionDirectoryTest`, `AdminPaymentHistoryTest`, `AdminBusinessDetailsTest`, `AdminEventCenterTest`, `AdminAuditLogTest`, `PaymentReviewAuditTest` |

### Billing submission and receipt privacy

`ManualPaymentSubmissionTest` covers server-priced payment submission, private local receipt storage, receipt extension/MIME validation, size limits, and tenant-scoped receipt access.

`ManualPaymentRejectionFlowTest` exercises the HTTP path across the business and platform-admin boundaries:

1. A business owner submits a receipt and bank tracking number for a selected plan.
2. The payment is persisted as `pending_review` at the server-defined plan price.
3. A Platform Admin rejects the payment through the admin HTTP endpoint.
4. The payment becomes `rejected`, while the trial subscription status, plan, and period timestamps remain unchanged.
5. The business billing page shows the rejection result and its own receipt link.
6. The business owner can still download its own private receipt.
7. The platform audit row identifies the admin and payment target but excludes the bank tracking number, rejection reason, and receipt storage path.

### Billing approval

`ManualPaymentApprovalFlowTest` exercises the positive HTTP path:

1. A business owner submits a receipt for the Professional plan.
2. A Platform Admin approves the payment through the admin HTTP endpoint.
3. The payment becomes `approved`, the subscription becomes active on the selected plan, and the paid period extends from the end of the still-valid trial.
4. The billing page reflects the approved plan and payment.
5. Both the owning tenant and Platform Admin can download the private receipt; another tenant receives 404.
6. The audit record attributes the action to the admin and excludes the bank tracking number and receipt path.

### PostgreSQL-specific guarantees

- `CatalogPostgresConcurrencyTest` verifies serialized quota enforcement, lock release after rollback, and independent writes for separate businesses.
- `SubscriptionPaymentPostgresConcurrencyTest` verifies concurrent manual approvals, callback idempotency, and serialization between subscription expiration and successful callback processing.
- These tests must execute on PostgreSQL; SQLite skips do not satisfy this acceptance requirement.

## Owner-controlled staging acceptance checklist

Run these checks against the isolated staging deployment, recording the build/commit, test date, result, and non-sensitive evidence for each item.

### Authentication and onboarding

- [ ] Register a new synthetic user and confirm the account is created.
- [ ] Request email verification and confirm the message is delivered to the controlled mailbox.
- [ ] Open the verification link and confirm the user becomes verified.
- [ ] Confirm an unverified user cannot enter verified-only application areas.
- [ ] Sign out, sign in, request a password reset, and complete it using the delivered link.
- [ ] Confirm invalid/expired verification and reset links are rejected.
- [ ] Create a business using each active plan option and confirm the selected plan and 72-hour trial are applied.
- [ ] Confirm a user who already owns a business cannot create a second onboarding business through the ordinary onboarding flow.

### Tenant isolation and authorization

- [ ] Create two unrelated businesses with separate owners and unique synthetic catalog data.
- [ ] As owner A, attempt to read and update business B by changing route identifiers, submitted IDs, and stale session context.
- [ ] Confirm no data belonging to business B is returned or modified.
- [ ] Confirm a manager can access only the actions granted to the manager role.
- [ ] Confirm receipt download by a different tenant returns 404 and does not disclose whether a private file exists.

### Plan restrictions and catalog

- [ ] On Economic, confirm product 21 and category 3 are rejected without creating records.
- [ ] On Economic, confirm unsupported discount/availability features are rejected.
- [ ] On Professional, confirm the sixth category is rejected and only the permitted image entitlement is exposed/enforced by implemented flows.
- [ ] On Top, confirm the configured product/category limits and currently implemented entitlements behave as expected.
- [ ] Confirm rejected requests do not leave orphaned rows or uploaded files.
- [ ] Confirm sorting, filtering, search, and pagination do not cross business/menu boundaries.

### Public menu and QR

- [ ] Publish a menu containing at least one active category and published/available product.
- [ ] Open the guest URL in a logged-out browser and confirm only public menu data is shown.
- [ ] Confirm drafts, unavailable products, inactive categories, unpublished menus, and suspended businesses are not exposed.
- [ ] Confirm administrative fields, private receipt paths, internal IDs, and other tenant data are absent from the public response.
- [ ] Unpublish the menu and confirm its public URL is no longer accessible.
- [ ] Generate and download the QR SVG from the application.
- [ ] Scan the downloaded QR with at least one real phone camera and confirm it opens the intended public menu over HTTPS.
- [ ] Print or display the QR at the intended practical size and confirm it remains scannable.

### Billing, receipts, and subscription lifecycle

- [ ] Submit a payment using a synthetic receipt and confirm the server-calculated plan price is used.
- [ ] Confirm receipt files are stored privately and cannot be fetched from a public URL.
- [ ] Confirm a tenant can access its own receipt but another tenant cannot.
- [ ] Reject a payment as Platform Admin and confirm the trial status and period remain unchanged.
- [ ] Approve a payment as Platform Admin and confirm the selected plan, feature snapshot, and paid period are updated according to the billing rules.
- [ ] Confirm duplicate review/callback attempts do not create duplicate successful payments or duplicate lifecycle events.
- [ ] Confirm the audit trail records the actor and safe transition details without receipt paths, bank tracking numbers, or rejection reasons.
- [ ] Confirm expiration changes subscription access without deleting business, menu, category, product, or receipt data.

### Operational smoke checks

- [ ] Confirm `/up` responds successfully on staging.
- [ ] Confirm the queue worker processes a harmless test job.
- [ ] Confirm the scheduler is active and the expiration command can be observed safely in staging.
- [ ] Confirm application logs do not contain credentials, API keys, raw receipt paths, or sensitive payment details.
- [ ] Confirm the production-like reverse proxy forwards HTTPS scheme/host correctly and cookies remain Secure, HttpOnly, and SameSite as configured.

## Acceptance evidence and exit criteria

Phase 19 must not be marked complete until all of the following are true:

1. The SQLite and PostgreSQL GitHub Actions jobs have a successful run for the candidate commit.
2. PostgreSQL-only concurrency tests have executed rather than skipped.
3. The staging checklist above is completed, with failures recorded and resolved or explicitly accepted as known risks.
4. The physical QR scan/print acceptance is recorded.
5. Receipt privacy and payment approval/rejection have been verified in the staging HTTP flow.
6. No destructive QA was performed against production.
7. The owner signs off the end-to-end staging acceptance.

Record the accepted commit SHA and date below when the owner completes the run:

- Accepted commit: _pending_
- Acceptance date: _pending_
- Owner sign-off: _pending_

## Out of scope for this MVP QA gate

Branch management, media management, visual templates, localization, custom domains, and the advanced Top-plan feature set belong to deferred product phases unless a separate launch requirement explicitly promotes one of them into scope.

## Current status

- Automated billing approval/rejection and receipt privacy scenarios exist.
- Authentication, tenant isolation, catalog restrictions, public menu, subscription lifecycle, and PostgreSQL concurrency test classes exist.
- This document change does not execute tests or staging acceptance.
- QR physical scan/print acceptance and the broader owner-controlled staging pass remain outstanding.
- CI success proves only the configured automated checks; it does not prove production deployment, external integrations, real email delivery, or physical QR scanning.
