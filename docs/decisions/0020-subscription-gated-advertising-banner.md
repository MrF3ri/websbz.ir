# ADR 0020: Subscription-Gated Public Menu Advertising Banner

- Status: Accepted for Phase 11 implementation
- Date: 2026-11-25
- Related: ADR 0015, public menu cache and subscription entitlements

## Context

Professional and Top plans include a public-menu advertising banner. Banner content may contain a call-to-action link, so input and output must be constrained. Existing banner content should remain stored after downgrade but must not remain publicly visible without entitlement.

## Decision

- Store one nullable JSON object on the menu with fixed fields: `title`, `message`, `cta_label`, and `cta_url`.
- Limit title to 100 characters, message to 300 characters, and CTA label to 40 characters.
- If a CTA label or URL is supplied, require both. Accept HTTPS URLs only; do not accept HTML, CSS, or script content.
- Enforce the `advertising_banner` subscription snapshot on the server when creating or changing non-empty banner content.
- Preserve an unchanged banner after downgrade and permit clearing it without the feature.
- Render the banner only when the current subscription snapshot enables the feature. Resolve the entitlement outside the cached public-menu payload so downgrades take effect immediately.
- Render all text as escaped plain text and open CTA links with `noopener noreferrer nofollow`.

## Consequences

- Economic plans cannot create or change a banner.
- Professional and Top plans can configure the fixed banner fields.
- Existing content is hidden, not deleted, after downgrade.
- CTA destinations are restricted to HTTPS and cannot inject markup.
