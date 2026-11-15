# ADR 0009: Catalog Schema and Composite Foreign Keys

- Status: Accepted
- Date: 2026-11-15

## Context
Every business has one primary menu. Categories belong to a menu and each product belongs to one category in one menu. Application-only checks are insufficient to prevent a product from referencing a category in another menu.

## Decision
- A business has exactly one primary menu, enforced by a unique constraint on menus.business_id.
- Menus, categories, and products expose unique ULID public identifiers while retaining BIGINT internal primary keys.
- Products do not duplicate business_id; ownership is resolved through menu -> business.
- categories has a unique (id, menu_id) key.
- products has a composite foreign key (category_id, menu_id) -> categories(id, menu_id).
- Category and product ordering is stable by sort_order then id.
- Money is stored as unsigned integer Toman. Images, public menu, branches, translations, and QR are out of scope for Phase 8.

## Consequences
Cross-menu product/category relationships are rejected by the database on both SQLite and PostgreSQL. Tenant ownership must still be scoped and authorized in application queries.
