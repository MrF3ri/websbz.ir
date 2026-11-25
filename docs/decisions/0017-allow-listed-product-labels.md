# ADR 0017: Allow-Listed Product Labels

- Status: Accepted for Phase 11 implementation
- Date: 2026-11-25
- Related: ADR 0015, Product subscription entitlements

## Context

Professional and Top subscriptions include product labels. Labels are presentation metadata for a product and must not become arbitrary HTML, CSS, or user-authored markup. Economic subscriptions do not include the feature.

## Decision

- Store one nullable `products.label` value.
- Restrict values to the server-owned `ProductLabel` enum: `new`, `popular`, `recommended`, and `chef_special`.
- Enforce the `product_labels` entitlement on product creation and on changes to a non-null label. Client-side disabled controls are not authorization.
- Preserve an existing label across a subscription downgrade when product details are edited without changing that label. Permit removing an existing label, but do not permit assigning a different label without entitlement.
- Include only the enum value in public menu data; the public renderer maps values to fixed presentation text and styles.
- Invalidate public-menu cache after product updates as already required by catalog mutations.

## Consequences

- Product labels are consistent and allow-listed across the admin and public menu.
- Economic tenants cannot add or change labels.
- Historical labels remain visible after downgrade until removed by an authorized manager.
- No arbitrary markup or style values are stored or rendered.
