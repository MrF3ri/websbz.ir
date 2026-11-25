# Phase 11 — Public Menu Customization

Status: In progress  
Current increment: 11.2 — Responsive previews and private business logos  
Implementation branch: `feat/phase-11-logo-and-previews`  
Pull request: #72 (Draft)

## Delivered in increment 11.1

- A server-owned registry of six stable template IDs: `classic`, `minimal`, `editorial`, `modern`, `cafe`, and `contrast`.
- Snapshot-based `menu_template_count` entitlement: Economic = 3, Professional = 6, Top = -1.
- Nullable `menus.theme` JSON storage with backward-compatible defaults.
- Tenant-scoped editor for template, primary color, font preset, and light/dark mode.
- Server-side entitlement checks; client-side disabled controls are not treated as authorization.
- Public renderer accepts only allow-listed theme tokens and validated hexadecimal colors.
- Public-menu cache invalidation after successful theme updates.
- Existing selected templates are preserved after a plan downgrade, but unavailable templates cannot be newly selected.

## Delivered in increment 11.2 (implementation in progress)

- Add mobile and desktop preview sizing for each available template in the theme editor.
- Add tenant-owned business-logo metadata linked to the existing private media table with a composite tenant foreign key.
- Add owner/admin-authorized logo upload and removal, gated by the `business_logo` subscription snapshot.
- Validate JPEG/PNG/WebP content, MIME/extension agreement, 2 MiB file size, and 2048 × 2048 maximum dimensions.
- Deliver logos through a public application route only for active businesses with a published menu; source objects remain on private storage.
- Invalidate public-menu cache after logo changes and clean up replaced media objects.
- Add ADR 0016 for the private logo storage and tenant isolation contract.

## Remaining Phase 11 work

1. Add visual regression coverage for all template previews and test mobile/desktop breakpoints.
2. Add plan-gated public-menu features: product labels, image slider, advertising banner, and business introduction.
3. Add Top-plan controlled custom-design settings without arbitrary user HTML, CSS, or JavaScript.
4. Verify accessibility, cache invalidation, tenant isolation, storage-failure recovery, and SQLite/PostgreSQL parity for all new features.

## Operational constraints

- No Production deployment is part of this PR.
- Existing subscription entitlement snapshots are immutable; do not rewrite historical snapshots as part of seeding.
- Keep private media private and serve it only through authorized application routes.
- Release remains blocked until the repository-wide Release Gate and owner sign-off are complete.

## CI verification

### Increment 11.1 — merged PR #71

- SQLite test suite and quality checks: passed (221 passed, 7 skipped).
- PostgreSQL test suite: passed (228 passed).
- Backblaze B2 S3 integration: passed in both database jobs.
- Production image, Compose, and Nginx validation: passed.

### Increment 11.2 — PR #72

- GitHub Actions verification is pending for the current branch head.
