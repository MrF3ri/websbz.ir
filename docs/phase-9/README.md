# Phase 9 — Branch Management

Status: In progress  
Current increment: 9.6 — PostgreSQL branch concurrency coverage  
Implementation branch: `test/phase-9-branch-postgres-concurrency`  
Approved model: Hybrid shared catalog with branch-level overrides (ADR 0014)

## Delivered foundation

- Branch schema and branch category/product settings with same-business and same-menu composite foreign keys.
- Primary-branch backfill and idempotent primary-branch initialization.
- Branch creation, profile updates, activation/deactivation, branch quota enforcement, and catalog assignment initialization.
- Regression coverage for branch lifecycle, primary-branch invariants, and composite foreign keys.
- Increment 9.1: pure effective product resolver for canonical-to-branch inheritance and overrides, with unit tests.

## Increment 9.2 — Branch-aware public menu projection

- Preserve the legacy `/menu/{business}/{menu}` route as an explicit primary-branch view.
- Resolve an optional branch slug only within the requested active Business; foreign and inactive branches return 404 and never fall back.
- Apply branch category visibility/order and product visibility/availability/stock/order through the shared resolver.
- Apply branch content and price/discount overrides without mutating canonical Product records.
- Include branch identity/profile data in the public projection.
- Scope the public-menu cache by branch and invalidate all branch projections when canonical menu data changes.
- Keep branch content overrides ahead of canonical translations; untranslated fields continue to use existing locale fallback.

## Increment 9.3 — Branch-specific QR targets

- Expose each active branch's stable public URL in the branch management page only while the canonical menu is published.
- Generate QR SVG locally in the browser using the existing QR utility; no third-party QR service receives the URL.
- Display and download a separate SVG QR for each active branch.
- Do not offer a QR for inactive branches or unpublished menus.
- Branch slugs are immutable after creation so previously printed branch QR codes keep resolving to the same branch.
- Preserve the existing primary-menu QR in the catalog page for backward compatibility.

## Increment 9.4 — Branch catalog settings management (delivered)

- Add a tenant-scoped branch catalog settings page linked from each branch.
- Manage branch category visibility/order and product visibility, availability, ordering, name/description overrides, base price, discount inheritance/set/clear, and optional manual stock counts.
- Preserve canonical catalog records; only branch settings rows are changed.
- Resolve the selected branch through the current tenant and validate every category/product public identifier against that tenant's canonical menu.
- Validate effective discounts against effective base prices and enforce consistent stock-tracking state.
- Serialize updates in a transaction coordinated by the Business row lock and invalidate the affected branch public-menu cache after a successful write.
- Add HTTP regression coverage for tenant isolation, override persistence, invalid discounts, and stock validation.

## Increment 9.5 — Public-menu cache invalidation audit (delivered)

- Audit canonical writes for categories, products, product images, menu translations/theme/banner/publication, and business profile/logo data. Their controllers call `forget($menu)`, which clears the legacy primary-menu key and every branch-specific key for that menu.
- Audit branch-only writes. Branch profile updates and branch catalog settings updates call `forget($menu, $branch)`, preserving other branches' cached projections.
- Branch creation starts with a new branch identifier and therefore has no prior cache key to invalidate. Activation/deactivation is checked before cache access and status is not part of the cached public projection.
- Add regression tests proving canonical invalidation clears the primary and branch projections, branch-scoped invalidation preserves the primary projection, and a canonical product update refreshes both public routes.

## Increment 9.6 — PostgreSQL branch concurrency coverage (in review)

- Add a PostgreSQL-only multi-process worker for branch creation and branch catalog settings writes.
- Prove two simultaneous branch creations serialize on the Business row lock and cannot exceed the Top plan's two-branch limit.
- Prove concurrent writes to separate branch-product settings preserve both updates and do not mutate canonical Product records.
- Prove a failed transaction after acquiring the Business lock releases it so a waiting branch-creation worker can proceed.
- Use PostgreSQL lock-wait observation rather than timing-only assertions; SQLite skips these tests because it does not provide the required row-lock semantics.

## Remaining Phase 9 work

1. Complete owner-driven end-to-end acceptance: create a second branch, customize it, scan its QR, and verify the primary branch is unchanged.

## Effective product rules

- Name: branch override, otherwise canonical name; existing translations apply only when no branch override exists.
- Description: branch override when enabled, including intentional null; otherwise canonical description and its translation fallback.
- Base price: branch override, otherwise canonical base price.
- Discount: set, clear, or inherit according to `discount_override_mode`.
- Availability: canonical availability and branch availability and either untracked stock or positive tracked stock.
- Visibility and sort order are branch-specific.

Do not duplicate these rules in controllers or Vue components.

## Operational constraints

- Preserve the canonical Business → Menu → Category/Product catalog.
- Do not create per-branch Product duplicates.
- Do not trust browser-supplied internal IDs or ownership witness fields.
- Keep stock informational/manual; no order reservation or automatic stock deduction.
- No Production deployment is part of this phase.
- Production readiness remains governed by the repository-wide Release Gate and owner sign-off.
