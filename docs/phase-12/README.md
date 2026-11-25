# Phase 12 — Localization and Translation Management

Status: Feature implementation complete; repository-wide Release Gate pending  
Current increment: 12.6 — PostgreSQL translation write concurrency (delivered)  
Implementation branch: `main`

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

## Delivered in increment 12.3 — merged PR #85

- Resolve the optional `lang` query parameter against the current business `max_languages` snapshot on every public-menu request.
- Keep the public menu path stable and expose only server-authorized locale choices.
- Render translated menu, category, product-name, and product-description fields with independent source-field fallback.
- Set document language and text direction from the locale registry.
- Add a Persian/English/Arabic language switcher and localized public-menu labels/number formatting.
- Keep translation records out of the browser payload after locale resolution.
- Version the locale-neutral public-menu cache payload.

## Increment 12.4 — translation completeness and validation

- Count a product-description translation as required for completeness only when its source description is non-empty.
- Keep the progress calculation in a pure typed helper with unit coverage for complete, incomplete, optional, and whitespace-only values.
- Show completed, total, and remaining field counts in the translation editor.
- Add a request-level regression test proving blank required menu/category/product text is rejected before any translation rows are persisted.

## Delivered in increment 12.4 — merged PR #86

- Count product-description translations only when the source product has a non-empty description.
- Extract completeness calculation into a typed pure helper.
- Add Vitest coverage for complete, incomplete, optional-description, and whitespace-only cases.
- Show completed, total, and remaining translation fields in the translation editor.
- Add request-level coverage proving blank required menu/category/product text is rejected before translation rows are persisted.

## Delivered in increment 12.5 — merged PR #87: translation tenant-isolation and cache regression coverage

- Verify a forged foreign active-business session cannot write translations for another tenant's menu.
- Verify translation writes invalidate a warm public-menu cache and translated content is visible on the next request.
- Preserve the existing per-menu transaction and row-lock boundary.

## Delivered in increment 12.6 — merged PR #95: PostgreSQL translation write concurrency

- Extract the existing per-menu transaction and row-lock logic into `SaveMenuTranslationsAction`; the HTTP controller remains responsible for tenant authorization, locale entitlement, and cache invalidation.
- Exercise concurrent writes for English and Arabic on the same menu using independent PostgreSQL connections.
- Verify each locale's menu/category/product translation rows persist without interleaving or loss.
- Force a failure after the menu row lock is acquired and verify a waiting locale write proceeds after rollback.
- Observe real PostgreSQL lock waits through `pg_stat_activity`; SQLite skips these tests because it does not provide the required row-lock semantics.

## Scope clarification

The approved normalized translation schema and Phase 12 tasks cover menu, category, product name, and product description fields. Advertising banner and business-introduction content are not included in that schema or the approved translation tasks, so they remain outside this increment unless a later product decision expands the scope.

## Remaining Phase 12 work

1. Include Phase 12 in the full repository Release Gate after all product phases are complete.

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

PR #95 passed the exact-head SQLite test/quality checks, PostgreSQL test suite, and production image/Compose validation before merge. The verified PR head was `0d72e2dd99062f39708c15d8d361e884e7af89d8`; merge commit: `47bb089fe34ab8988020a9c4e317d5b407b1d5be`.
