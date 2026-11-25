# ADR 0021: Top-Only Controlled Custom Design Tokens

- Status: Accepted for Phase 11 implementation
- Date: 2026-11-25
- Related: ADR 0015, Menu theme registry

## Context

The Top plan includes controlled custom design settings. The system must not accept arbitrary user HTML, CSS, JavaScript, class names, or style declarations. Existing design choices should remain stable after a subscription downgrade.

## Decision

- Add three fixed menu theme tokens:
  - `density`: `compact`, `comfortable`, `spacious`.
  - `card_style`: `standard`, `flat`, `elevated`.
  - `heading_size`: `normal`, `large`, `display`.
- Defaults are `comfortable`, `standard`, and `normal`.
- Enable non-default values only when the current subscription snapshot includes `custom_design` (Top).
- Preserve existing non-default values across downgrade when settings are unchanged. Permit resetting any token to its default without the feature; deny changing a token to another non-default value.
- Resolve public presentation through fixed allow-lists and fixed class maps. Never interpolate user-provided CSS or HTML.
- Keep theme updates transactional and invalidate the public-menu cache after a successful update.
- Version the public-menu cache key when the cached theme payload shape changes.

## Consequences

- Top owners can choose from the controlled density, card, and heading options.
- Professional and Economic owners cannot assign new non-default custom design values.
- Existing values remain visible after downgrade until reset to defaults.
- Public rendering remains constrained to application-owned class maps and token allow-lists.
