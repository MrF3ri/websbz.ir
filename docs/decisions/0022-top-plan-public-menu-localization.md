# ADR 0022: Top-plan public-menu localization model

- Status: Accepted for Phase 12 implementation
- Date: 2026-11-25

## Context

The approved product specification enables Persian, English, and Arabic public menus only for the Top plan. Economic and Professional remain single-language. The menu already has a `default_locale` column (default `fa`) and subscription snapshots already contain `max_languages`.

The current menu, category, and product tables store their primary display text directly. The application has no translation tables or centralized locale registry.

## Decision

1. Define one allow-list of public-menu locales: `fa`, `en`, and `ar`, with direction metadata (`rtl`, `ltr`, `rtl`).
2. Use the existing `max_languages` entitlement snapshot as the single source of truth. Economic and Professional receive only the default locale; Top receives all three.
3. Keep the existing `menus.title`, `categories.name`, and `products.name/description` columns as the source-language values for the menu's `default_locale` (currently `fa`). Store alternate-language content in dedicated `menu_translations`, `category_translations`, and `product_translations` tables.
4. Enforce one translation per entity and locale with unique constraints. Translation rows reference their owning menu/category/product by foreign key and cascade with that entity.
5. Keep product prices, availability, labels, images, sort order, and branch overrides language-neutral. Only display text is translated.
6. Resolve requested locales against the business entitlement before reading translations. Unsupported or unavailable locales must never be exposed as an enabled language. Missing translations fall back to the entity's source-language fields.
7. Keep public menu URLs and entity public IDs stable; language selection must not change tenant resolution or authorization.
8. Set page direction from the resolved locale metadata, not from user-supplied values.

## Consequences

- Existing Persian content remains valid without a data migration or backfill.
- Translation data is normalized and independently constrained.
- The public renderer can load the source text and optional translation rows without duplicating business/catalog records.
- Translation management must enforce locale availability server-side; hiding language controls in Vue is not authorization.
- Adding a new locale requires an explicit allow-list change, UI direction metadata, and localization test coverage.
