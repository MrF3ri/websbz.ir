# Phase 9 — Branch Management

Status: In progress  
Current increment: 9.1 — Effective branch product resolver  
Implementation branch: `feat/phase-9-branch-public-menu`  
Approved model: Hybrid shared catalog with branch-level overrides (ADR 0014)

## Existing foundation

- Branch schema and branch category/product settings with same-business and same-menu composite foreign keys.
- Primary-branch backfill and idempotent primary-branch initialization.
- Branch creation, profile updates, activation/deactivation, branch quota enforcement, and catalog assignment initialization.
- Regression coverage for branch lifecycle, primary-branch invariants, and composite foreign keys.

## Increment 9.1 — effective branch product resolver

- Add one typed domain resolver for canonical product values plus optional branch settings.
- Resolve name, description (including intentional clear-to-null), base price, discount inherit/set/clear, visibility, availability, inventory, and sort order.
- Keep the resolver pure: it does not mutate canonical products or branch setting models.
- Add unit coverage for inheritance, overrides, discount clearing, visibility, availability, and tracked/untracked stock behavior.

## Remaining Phase 9 work

1. Integrate the resolver into the public menu read path with explicit primary-branch compatibility behavior.
2. Add stable branch-specific public menu URLs; inactive or foreign branches must never fall back to another branch.
3. Implement branch category visibility/order and product visibility/order in the public projection.
4. Add tenant-scoped management for category and product branch settings, including explicit clear/inherit semantics.
5. Add branch-specific public menu QR targets.
6. Add PostgreSQL concurrency coverage for branch quota and settings writes.
7. Complete owner-driven end-to-end acceptance: create a second branch, customize it, scan its QR, and verify the primary branch is unchanged.

## Effective product rules

The public resolver must follow ADR 0014:

- Name: branch override, otherwise canonical name.
- Description: branch override when enabled, including intentional null; otherwise canonical description.
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

## CI verification

GitHub Actions verification is pending for the current branch head.
