# Entitlement Enforcement Contract

## Overview

This document defines the architectural contract between the **Subscription Module** (decision engine) and **consumer modules** (for example, Catalog and Branch).

The `EnforcementService` evaluates access and limits using the business's entitlement state. Consumer modules are responsible for supplying trustworthy usage data, enforcing the correct resource scope, and securing the operation that consumes capacity.

This contract is a mandatory implementation requirement for every consumer module that introduces plan-gated features or resource limits.

## Mandatory Implementation Rules for Consumer Modules

### 1. Server-Side Usage Calculation

Never trust client input for `currentCount`. Calculate usage from the database on the server, scoped to the authenticated and authorized Business.

**Wrong:**

```php
$enforcement->requireLimit(
    $business,
    'max_products',
    $request->input('current_products_count'),
);
```

**Correct:**

```php
$enforcement->requireLimit(
    $business,
    'max_products',
    $business->products()->count(),
);
```

The example assumes the relationship is itself correctly scoped to the Business.

### 2. Resource Scope Precision

Usage calculation must respect the logical scope of the resource being limited.

For `max_product_images`, count the images attached to the **specific product** being edited or uploaded to. Do not count all images belonging to the Business unless the product specification explicitly defines a business-wide quota.

### 3. Concurrency Safety and Race Conditions

A check followed by an insert is vulnerable to a time-of-check/time-of-use race when concurrent requests compete for the final available slot.

The capacity check and resource creation must therefore execute in a database transaction, with a pessimistic lock on a stable row that serializes competing operations for the same quota (for example, the Business row for a business-wide quota or the Product row for a per-product quota).

Required sequence:

1. Begin a database transaction.
2. Lock the row that coordinates access to the relevant quota using `lockForUpdate()`.
3. Recalculate the current usage from the database inside the transaction.
4. Validate the recalculated usage through `EnforcementService`.
5. Create the resource and commit the transaction.

A transaction by itself, or applying `lockForUpdate()` to an aggregate count query without a suitable coordinating row, is not sufficient to guarantee serialization.

### 4. Absolute Feature Enforcement

Boolean features (for example, `custom_domain`) must be enforced on the backend at the Controller, FormRequest, Action, or domain-service boundary.

Hiding a button or menu item in the frontend is only a UX enhancement. It is not an authorization or security control.

### 5. Strict Tenant Isolation

Usage from Business A must never affect, or be exposed through, Business B's usage calculation.

Consumer modules must resolve the authorized Business through the established tenant context (for example, the authenticated user's current Business as resolved by the application's tenant middleware) and scope every usage query to that Business. Never accept a Business identifier from the client as sufficient proof of tenant authorization.

### 6. Integration Testing Requirements

Every consumer module that implements plan limits must provide HTTP Feature tests proving all of the following:

1. The limit cannot be bypassed through an HTTP request.
2. The application returns **403 Forbidden** when the applicable limit or feature restriction is reached.
3. **No resource is persisted** when the request is denied.
4. Usage calculations are isolated between separate Businesses.
5. Concurrent requests cannot exceed the configured quota.

Tests must exercise the real consumer-module route and persistence path. A test-only or dummy route that verifies Laravel's exception-to-HTTP response conversion is useful supplementary coverage, but it does **not** satisfy consumer-module integration testing requirements.

## Phase 6 Scope and Handoff

Phase 6 delivers the Subscription entitlement decision and enforcement core:

- Plan and feature/limit definitions.
- Subscription state and entitlement snapshot resolution.
- `EntitlementService` for entitlement queries.
- `EnforcementService` for feature and quota decisions.

Catalog, Branch, and other consumer modules own the database usage calculation, resource-specific scope, transactional concurrency controls, and real-route integration tests described above. Those responsibilities are intentionally deferred until the corresponding consumer modules exist.

The existence of this contract records the required handoff; it does not claim that future consumer-module enforcement has already been implemented.
