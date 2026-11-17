# ADR 0014: Branches with Shared Catalog and Branch-Level Product Overrides

- Status: Accepted
- Date: 2026-11-17
- Decision owner: Product owner
- Related: ADR 0001, ADR 0009, ADR 0010, ADR 0003, Issue #64

## Context

The product owner selected the **hybrid** branch model:

- Every branch has independent business/location information and its own public menu identity and QR code.
- Product catalog data is shared by default across branches.
- A branch may override product content when its menu differs from other branches.
- Price, stock/availability, visibility, and display order are branch-specific.

The current schema has one primary Menu per Business, and Product/Category records belong to that Menu. Product currently stores name, description, base price, discount price, availability, publication status, and sort order. Branches do not yet exist. Replacing the current catalog with duplicated per-branch products would create unnecessary duplication and make central maintenance difficult.

## Decision

### 1. Shared canonical catalog

Keep the current Business -> Menu -> Category/Product ownership model:

- One canonical Menu remains associated with each Business.
- Categories and Products remain canonical business-level records under that Menu.
- Canonical Product fields remain the defaults: name, description, base price, discount price, status, and other shared product metadata.
- A product is not copied into a separate Product row for each branch.

This preserves existing Catalog behavior and provides one source of truth for common product information.

### 2. Branch identity and profile

Introduce a tenant-owned `branches` table:

| Column | Rule |
|---|---|
| id | BIGINT internal primary key |
| public_id | Unique ULID; public identifier only, never authorization |
| business_id | Required tenant FK |
| name | Required branch display name |
| slug | URL-safe branch slug, unique within the Business |
| status | Active/inactive lifecycle state |
| phone, email | Optional branch contact details |
| address | Optional branch address |
| latitude, longitude | Optional coordinates; validate as a pair and within geographic bounds |
| timezone | IANA timezone, default from the product/business policy; do not infer from browser |
| opening_hours | Nullable validated structured schedule; no arbitrary unvalidated client JSON |
| timestamps | Standard timestamps |

Constraints:
- Unique `(business_id, slug)`.
- Unique `(id, business_id)` to support composite ownership references.
- A Business must have at least one active/default branch after Phase 9 migration.
- The first branch is marked as the primary branch using a single, database-enforced primary marker (or equivalent unique partial-index strategy compatible with SQLite and PostgreSQL). Do not rely on a frontend convention.
- The default branch is not deletable while it is the only branch. Branch deletion/deactivation must not cascade-delete canonical products or historical business data.

### 3. Branch-specific category settings

Introduce `branch_category_settings` to control category presentation per branch without duplicating categories:

| Column | Rule |
|---|---|
| id | BIGINT |
| branch_id, business_id | Branch ownership pair |
| menu_id | Canonical menu |
| category_id | Canonical category |
| is_visible | Whether this category is shown in this branch |
| sort_order | Branch-specific category order |
| timestamps | Standard timestamps |

Unique `(branch_id, category_id)`. Composite foreign keys must prove that the branch and category belong to the same Business/Menu. A category with no explicit setting follows the Phase 9 default policy; the migration/backfill must make that policy deterministic.

### 4. Branch-specific product assignment and overrides

Introduce `branch_product_settings` (or an equivalently named explicit domain table; avoid a generic untyped JSON settings blob):

| Column | Rule |
|---|---|
| id | BIGINT |
| branch_id, business_id | Branch ownership pair |
| menu_id | Canonical menu |
| product_id | Canonical product |
| name_override | Nullable; null means inherit canonical name |
| description_override | Nullable text |
| description_override_enabled | Boolean; distinguishes inherit from intentionally clearing the description |
| base_price_override | Nullable unsigned integer Toman; null means inherit canonical base price |
| discount_price_override | Nullable unsigned integer Toman |
| discount_override_mode | Enum/string: inherit, set, clear; default inherit |
| is_visible | Branch-specific product inclusion in the public menu |
| is_available | Branch-specific availability toggle |
| track_inventory | Whether a numeric stock count is tracked |
| stock_quantity | Nullable non-negative integer; required when track_inventory is true, otherwise null |
| sort_order | Branch-specific order within the category |
| timestamps | Standard timestamps |

Constraints and invariants:
- Unique `(branch_id, product_id)`.
- Unique/parent keys on branches `(id, business_id)`, menus `(id, business_id)`, and products `(id, menu_id)`.
- The settings row carries `business_id` and `menu_id` as ownership witnesses. Composite FKs must enforce:
  - `(branch_id, business_id) -> branches(id, business_id)`
  - `(menu_id, business_id) -> menus(id, business_id)`
  - `(product_id, menu_id) -> products(id, menu_id)`
  This prevents a branch from being associated with a product from another Business even if application validation is bypassed.
- Never trust branch_id/business_id from the browser as authorization. Resolve the Business through CurrentBusiness and authorize the branch through its tenant ownership.
- Money remains unsigned integer Toman, consistent with ADR 0009.
- `discount_override_mode=set` requires a valid override amount; `clear` explicitly removes a canonical discount; `inherit` uses the canonical discount. Validate the effective discount against the effective base price.
- `description_override_enabled=false` means inherit; true with null means intentionally no description.
- `track_inventory=false` means stock is not tracked; `track_inventory=true` requires stock_quantity >= 0. Stock quantity zero makes the branch product unavailable to customers, but does not delete or hide it from the manager.
- Phase 9 inventory is a manually maintained availability count only. There is no order, reservation, decrement, warehouse, or stock-ledger system in this phase. Do not imply real-time inventory deduction until an ordering workflow exists.
- Effective public values are resolved centrally in a BranchProductResolver/service, not independently in controllers or Vue components.

### 5. Default assignment and inheritance behavior

- The Phase 9 migration creates one primary branch for each existing Business.
- Existing canonical Products and Categories are made visible in that primary branch with inherited product content, inherited prices/discounts, and the current availability/status behavior preserved.
- Creating a new branch initially assigns all existing canonical products and categories as visible with inherited content/prices. The owner may then hide or override them.
- Creating a canonical product after branches exist creates a visible, inherited assignment for each active branch by default. If a product is intentionally excluded from a branch, that exclusion must remain explicit and must not be silently reset by later catalog edits.
- Editing canonical product content changes inherited values only. It must never overwrite branch overrides.
- Clearing an override returns the field to inheritance; it must not copy canonical values into branch override columns.
- Public rendering uses canonical Product status plus branch category/product visibility and branch availability/stock rules. A product is public only if its canonical status is publishable and its branch assignment/category are visible and available under the defined rules.

### 6. Public menu and QR identity

- Each active branch has a stable public menu URL and a QR code resolving to that branch's public menu.
- Public identity uses the branch public identifier/slug route design approved in implementation; ULID is not an authorization secret.
- The existing business-level public menu behavior must remain backward-compatible during rollout. Redirect or default-branch resolution must be explicit and tested.
- QR generation remains local/browser-side as in Phase 14; no third-party QR service receives private menu URLs.
- Deactivating a branch must produce a deliberate public response (for example, unavailable page), not accidentally display another branch's menu.

### 7. Entitlements and concurrency

- Branch count is enforced through the existing Subscription Entitlement/Enforcement services; frontend hiding is not enforcement.
- The primary/default branch counts toward `max_branches`. A business cannot create an extra branch by bypassing the UI.
- Branch creation, branch-product assignment, and quota-sensitive writes use transactions and a stable coordinating row lock. Recalculate usage inside the transaction.
- Branch-level stock edits and settings writes must be tenant- and branch-scoped. Use optimistic or pessimistic concurrency where concurrent edits could overwrite critical stock state.
- PostgreSQL integration tests are required for quota races and composite-FK behavior; SQLite tests remain useful but do not prove PostgreSQL locking semantics.

### 8. Phase boundaries

Phase 9 includes:
- Branch CRUD, active/inactive lifecycle, primary branch, profile/contact/location/opening-hours data.
- Branch category visibility/order.
- Branch product assignment, inherited canonical fields, branch overrides, price, discount, availability, simple stock count, visibility, and ordering.
- Branch-specific public menu identity and QR target.
- Migration/backfill, tenant/branch authorization, entitlement enforcement, audit-relevant events, and PostgreSQL tests.

Deferred:
- Media assets and per-branch image overrides (Phase 10; schema extension must preserve the same inheritance contract).
- Advanced menu templates/design tokens (Phase 11).
- Translations and locale-specific overrides (Phase 12).
- Orders, reservations, automatic stock deduction, warehouses, and stock movement ledger (not part of Phase 9).
- Multi-menu per branch or multiple menus per business (not approved by this ADR).

## Consequences

### Positive
- One canonical product catalog avoids duplication and keeps central updates efficient.
- Branches can tailor content and operations without forcing identical menus.
- Database constraints reinforce tenant boundaries.
- Existing single-branch businesses can be migrated with a predictable default branch.
- Public menu and QR identity becomes branch-aware.

### Costs and risks
- Effective values require a single resolver and disciplined query design.
- Branch overrides add joins and cache invalidation dimensions; public-menu cache keys must include branch identity and writes must invalidate only/appropriately all affected branch caches.
- Backfill must be idempotent and safe for existing production data.
- Product creation/update flows must maintain branch assignment rows transactionally.
- A future per-branch media override must extend this model rather than creating a parallel untyped configuration mechanism.

## Required acceptance tests

1. Existing Business receives exactly one primary branch and all current products/categories remain visible through it.
2. New branch gets inherited product/category assignments.
3. New canonical product is assigned to active branches according to the default policy.
4. Canonical edits update inherited fields but do not overwrite branch overrides.
5. Branch override can replace and later clear name, description, price, and discount behavior correctly.
6. Branch availability, stock count, visibility, category visibility, and order are independent across branches.
7. Zero tracked stock is unavailable; untracked stock does not block availability.
8. A branch cannot reference another Business's product/category, at both HTTP and database constraint layers.
9. Business A cannot read, edit, deactivate, or expose Business B's branch by guessing IDs/ULIDs.
10. Public URL and QR resolve only the intended branch; inactive branch does not fall back to another branch.
11. Branch quota is enforced server-side and concurrent requests cannot exceed it on PostgreSQL.
12. Public-menu cache is branch-aware and invalidation covers canonical and branch-specific writes.
13. Migration/backfill is idempotent, tested against a production-like copy, and never run as destructive QA on live production.

## Rollout

1. Implement schema and backfill in a dedicated reviewed migration.
2. Deploy code that can read the new branch model while preserving the legacy public route.
3. Backfill the primary branch and branch assignments transactionally/batched with progress and retry safety.
4. Verify row counts, ownership constraints, public menu output, and QR target in staging.
5. Switch public routing to branch-aware URLs only after acceptance.
6. Keep rollback strategy documented; do not drop legacy behavior in the same release as initial backfill.
