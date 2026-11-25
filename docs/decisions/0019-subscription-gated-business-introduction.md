# ADR 0019: Subscription-Gated Business Introduction

- Status: Accepted for Phase 11 implementation
- Date: 2026-11-25
- Related: ADR 0015, Business subscription entitlements

## Context

The public menu can show a short introduction about the business. This is a Professional/Top feature and must be enforced server-side. Existing content should not be destructively removed when a subscription is downgraded.

## Decision

- Store a nullable plain-text `businesses.introduction` value with a maximum of 1200 characters.
- Gate new or changed non-empty text through the `business_introduction` subscription snapshot in the business settings controller.
- Preserve an existing introduction when an update omits it or resubmits the same value after downgrade. Permit clearing the field without the feature.
- Render the introduction in the public menu only when the current subscription snapshot enables the feature.
- Resolve the entitlement outside the cached public-menu payload so downgrades take effect immediately while the stored text remains intact.
- Render plain text only; do not accept HTML, CSS, or script markup.

## Consequences

- Economic tenants cannot add or change an introduction.
- Professional and Top tenants can edit up to 1200 characters.
- Existing text remains stored after downgrade but is hidden from the public menu until the feature is restored.
- Authorized owners can remove existing text after downgrade.
