# Phase 12 — Localization and Translation Management

Status: In progress  
Current increment: 12.1 — Locale registry and translation schema  
Implementation branch: `feat/phase-12-localization`

## Approved scope

Support multilingual public menus for Top only:

- Persian (`fa`) — RTL
- English (`en`) — LTR
- Arabic (`ar`) — RTL

Economic and Professional remain single-language. Locale availability is controlled by the existing `max_languages` subscription snapshot.

## Delivered in increment 12.1 (implementation in progress)

- Add a centralized supported-locale registry with display names and direction metadata.
- Resolve the available locale list from the current business subscription snapshot.
- Add normalized translation tables for menus, categories, and products.
- Add Eloquent translation models and relationships.
- Preserve existing menu/category/product text as the source-language values for `menus.default_locale` (currently Persian).
- Record the storage and entitlement decision in ADR 0022.

## Acceptance criteria

1. Only `fa`, `en`, and `ar` are accepted by the public-menu locale registry.
2. Economic and Professional resolve to `fa` only.
3. Top resolves to `fa`, `en`, and `ar`.
4. Direction metadata is RTL for Persian/Arabic and LTR for English.
5. Each entity has at most one translation per locale.
6. Deleting a menu/category/product cascades to its translations.
7. Unsupported locales never become enabled through request parameters or client-side state.
8. Missing translations fall back to the source-language field.
9. Prices, availability, labels, images, and branch overrides remain language-neutral.
10. Public menu URL and tenant resolution remain unchanged.

## Remaining Phase 12 work

1. Implement tenant-scoped translation CRUD and Top-only authorization.
2. Add validation and translation completeness indicators for menu/category/product text.
3. Add a public-menu language switcher with locale entitlement enforcement.
4. Render translated text with correct page language and direction.
5. Implement deterministic fallback for missing translation fields.
6. Add SQLite/PostgreSQL coverage for translation CRUD, entitlement changes, fallback, and cross-tenant access.

## Operational constraints

- Do not change historical subscription snapshots.
- Do not enable multilingual menus for Economic or Professional.
- Do not translate monetary values, product IDs, or public URLs.
- No Production deployment is part of this phase.
- Production readiness remains governed by the repository-wide Release Gate and owner sign-off.

## CI verification

GitHub Actions verification is pending for the current branch head.
