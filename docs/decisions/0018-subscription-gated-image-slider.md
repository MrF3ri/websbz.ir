# ADR 0018: Subscription-Gated Public Menu Image Slider

- Status: Accepted for Phase 11 implementation
- Date: 2026-11-25
- Related: ADR 0015, Phase 10 responsive product images

## Context

Professional and Top subscriptions enable an image slider. The public menu already exposes responsive product images, but the presentation mode must follow the active subscription snapshot. Subscription entitlements are mutable only through the subscription lifecycle and public-menu catalog payloads are cached.

## Decision

- Keep responsive image URLs, srcset, sizes, and dimensions as the canonical image data.
- Render a horizontal scroll-snap slider with previous/next controls only when the current subscription snapshot enables `image_slider` and a product has more than one image.
- Render the existing responsive image grid when the feature is unavailable or there is only one image.
- Use fixed application markup and classes; do not accept user-supplied HTML or CSS.
- Resolve `image_slider` outside the cached public-menu catalog payload so a subscription change takes effect immediately without requiring a menu cache purge.
- Version the public-menu cache key when the payload shape changes, so cached entries created before product public IDs, slider fields, and custom design tokens are added cannot be reused.
- Make the slider keyboard-focusable and provide accessible region labels and control labels.

## Consequences

- Economic plans continue to see the responsive grid.
- Professional and Top plans see the slider for products with multiple images.
- The same responsive image delivery and private/public media controls remain in effect.
