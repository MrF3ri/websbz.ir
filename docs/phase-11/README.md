# Phase 11 — Public Menu Customization

Status: In progress  
Current increment: 11.1 — Theme foundation and template entitlements  
Implementation branch: `feat/phase-11-menu-themes`  
Pull request: #71 (Draft)

## Delivered in increment 11.1

- A server-owned registry of six stable template IDs: `classic`, `minimal`, `editorial`, `modern`, `cafe`, and `contrast`.
- Snapshot-based `menu_template_count` entitlement: Economic = 3, Professional = 6, Top = -1.
- Nullable `menus.theme` JSON storage with backward-compatible defaults.
- Tenant-scoped editor for template, primary color, font preset, and light/dark mode.
- Server-side entitlement checks; client-side disabled controls are not treated as authorization.
- Public renderer accepts only allow-listed theme tokens and validated hexadecimal colors.
- Public-menu cache invalidation after successful theme updates.
- Existing selected templates are preserved after a plan downgrade, but unavailable templates cannot be newly selected.

## Remaining Phase 11 work

1. Add preview cards and responsive previews for every template, with visual regression coverage.
2. Complete the logo workflow using private media storage, tenant ownership checks, replacement cleanup, and entitlement enforcement.
3. Add plan-gated public-menu features: product labels, image slider, advertising banner, and business introduction.
4. Add Top-plan controlled custom-design settings without arbitrary user HTML, CSS, or JavaScript.
5. Verify accessibility, mobile behavior, cache invalidation, tenant isolation, and SQLite/PostgreSQL parity for all new features.

## Operational constraints

- No Production deployment is part of this PR.
- Existing subscription entitlement snapshots are immutable; do not rewrite historical snapshots as part of seeding.
- Keep private media private and serve it only through authorized application routes.
- Release remains blocked until the repository-wide Release Gate and owner sign-off are complete.

## CI verification for the current PR head

- SQLite test suite and quality checks: passed (220 passed, 7 skipped).
- PostgreSQL test suite: passed (227 passed).
- Production image, Compose, and Nginx validation: passed.
