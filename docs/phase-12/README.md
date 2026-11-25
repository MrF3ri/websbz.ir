# Phase 12 — Localization and Translation Management

Status: In progress  
Current increment: 12.3 — Public menu locale resolution and rendering  
Implementation branch: `feat/phase-12-public-menu-locales`

## Approved scope

Support multilingual public menus for Top only:

- Persian (`fa`) — RTL
- English (`en`) — LTR
- Arabic (`ar`) — RTL

Economic and Professional remain single-language. Locale availability is controlled by the existing `max_languages` subscription snapshot.

## Delivered in increment 12.1 — merged PR #83

- Add a centralized supported-locale registry with display names and direction metadata.
- Resolve the available locale list from the current business subscription snapshot.
- Add normalized translation tables for menus, categories, and products.
- Add Eloquent translation models and relationships.
- Preserve existing menu/category/product text as the source-language values for `menus.default_locale` (currently Persian).
- Record the storage and entitlement decision in ADR 0022.

## Delivered in increment 12.2 — merged PR #84

- Add owner-authorized translation editor routes scoped to the authenticated current business.
- Enforce locale availability through the existing subscription snapshot; the default locale is source text and cannot be edited as an alternate translation.
- Save menu, category, and product translations atomically.
- Require submitted category and product public-ID sets to match the current menu exactly, rejecting cross-menu IDs and omissions.
- Add a Top-only translation editor with source-text context, locale direction, and completion progress.
- Show the translation navigation item only when `max_languages` exceeds one.

## Increment 12.3 — public menu locale resolution and rendering

- Resolve the requested public locale from the `lang` query parameter against the current subscription snapshot on every request.
- Keep the public menu path stable; locale selection does not alter business/menu resolution or public identifiers.
- Render translated menu, category, product-name, and product-description fields.
- Fall back independently per field to the source-language value when a translation is missing.
- Expose only the currently entitled locale options to the public language switcher.
- Set the document language and text direction from the server-resolved locale metadata.
- Localize the public menu's interface labels and number formatting for Persian, English, and Arabic.
- Keep prices, discounts, availability, labels, images, and branch overrides language-neutral.
- Bump the public-menu cache payload version so pre-localization cached payloads cannot be reused.

## Remaining Phase 12 work

1. Add validation and translation completeness indicators for menu/category/product text.
2. Review translation behavior for banner and business-introduction fields; these fields are not part of the approved normalized translation tables yet.
3. Add broader SQLite/PostgreSQL coverage for concurrent translation writes and tenant isolation.
4. Reconcile the phase roadmap and run the full repository Release Gate when all product phases are complete.

## Acceptance criteria

1. Only `fa`, `en`, and `ar` are accepted by the public-menu locale registry.
2. Economic and Professional resolve to `fa` only.
3. Top resolves to `fa`, `en`, and `ar`.
4. Direction metadata is RTL for Persian/Arabic and LTR for English.
5. Each entity has at most one translation per locale.
6. Deleting a menu/category/product cascades to its translations.
7. Unsupported or unavailable locales fall back to the menu's default locale.
8. Missing translation fields fall back independently to source-language fields.
9. Prices, availability, labels, images, and branch overrides remain language-neutral.
10. Public menu URL and tenant resolution remain unchanged.

## Operational constraints

- Do not change historical subscription snapshots.
- Do not enable multilingual menus for Economic or Professional.
- Do not translate monetary values, product IDs, or public URLs.
- No Production deployment is part of this phase.
- Production readiness remains governed by the repository-wide Release Gate and owner sign-off.

## CI verification

GitHub Actions verification is pending for the current branch head.
