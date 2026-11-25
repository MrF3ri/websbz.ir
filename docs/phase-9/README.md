# Phase 9 — Branch Management

Status: In progress  
Current increment: 9.3 — Branch-specific public menu QR  
Implementation branch: `feat/phase-9-branch-menu-qr`  
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

## Remaining Phase 9 work

1. Add tenant-scoped management UI and actions for category/product branch settings, including explicit clear/inherit semantics and validation of effective discounts and stock.
2. Audit every canonical and branch-specific write path for correct branch-cache invalidation.
3. Add PostgreSQL concurrency coverage for branch quota and settings writes.
4. Complete owner-driven end-to-end acceptance: create a second branch, customize it, scan its QR, and verify the primary branch is unchanged.

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
