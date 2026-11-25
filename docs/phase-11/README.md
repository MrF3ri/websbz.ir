# Phase 11 — Public Menu Customization

Status: In progress  
Current increment: 11.4 — Subscription-gated image slider  
Implementation branch: `feat/phase-11-image-slider`  
Pull request: #74 (Draft)

## Delivered in increment 11.1

- A server-owned registry of six stable template IDs: `classic`, `minimal`, `editorial`, `modern`, `cafe`, and `contrast`.
- Snapshot-based `menu_template_count` entitlement: Economic = 3, Professional = 6, Top = -1.
- Nullable `menus.theme` JSON storage with backward-compatible defaults.
- Tenant-scoped editor for template, primary color, font preset, and light/dark mode.
- Server-side entitlement checks; client-side disabled controls are not treated as authorization.
- Public renderer accepts only allow-listed theme tokens and validated hexadecimal colors.
- Public-menu cache invalidation after successful theme updates.
- Existing selected templates are preserved after a plan downgrade, but unavailable templates cannot be newly selected.

## Delivered in increment 11.2

- Add mobile and desktop preview sizing for each available template in the theme editor.
- Add tenant-owned business-logo metadata linked to the existing private media table with a composite tenant foreign key.
- Add owner/admin-authorized logo upload and removal, gated by the `business_logo` subscription snapshot.
- Validate JPEG/PNG/WebP content, MIME/extension agreement, 2 MiB file size, and 2048 × 2048 maximum dimensions.
- Deliver logos through a public application route only for active businesses with a published menu; source objects remain on private storage.
- Invalidate public-menu cache after logo changes and clean up replaced media objects.
- Add ADR 0016 for the private logo storage and tenant isolation contract.

## Delivered in increment 11.3

- Add a server-owned product-label registry (`new`, `popular`, `recommended`, `chef_special`).
- Gate new and changed labels through the `product_labels` subscription snapshot on the server.
- Preserve existing labels across downgrade when product details are edited; allow label removal without the feature.
- Display labels in the catalog editor and public menu using fixed text and styles.
- Add SQLite/PostgreSQL tests for entitlement enforcement, downgrade behavior, and public rendering.
- Record the allow-list and downgrade behavior in ADR 0017.

## Delivered in increment 11.4 (implementation in progress)

- Add a subscription-snapshot-driven image slider to public product cards when more than one image exists.
- Keep the responsive image grid for Economic plans and products with one image.
- Add keyboard focus, previous/next controls, scroll-snap behavior, and accessible labels.
- Resolve the `image_slider` entitlement outside the cached public-menu payload so subscription changes take effect immediately.
- Add coverage for entitlement changes while the public-menu catalog cache remains warm.
- Record the behavior in ADR 0018.

## Delivered in increment 11.4 (implementation in progress)

- Add a subscription-snapshot-driven image slider to public product cards when more than one image exists.
- Keep the responsive image grid for Economic plans and products with one image.
- Add keyboard focus, previous/next controls, scroll-snap behavior, and accessible labels.
- Resolve the `image_slider` entitlement outside the cached public-menu payload so subscription changes take effect immediately.
- Add coverage for entitlement changes while the public-menu catalog cache remains warm.
- Record the behavior in ADR 0018.

## Remaining Phase 11 work

1. Add visual regression coverage for all template previews and test mobile/desktop breakpoints.
2. Add plan-gated public-menu components: advertising banner and business introduction.
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

### Increment 11.2 — merged PR #72

- SQLite: 230 passed, 7 skipped (483 assertions).
- PostgreSQL: 237 passed (566 assertions).
- BusinessLogoTest and Backblaze B2 S3 integration: passed.
- Frontend unit tests: 7 passed; Vue/TypeScript checks passed.
- Pint: 236 files passed; PHPStan reported no errors.
- Production image build, Compose validation, and Nginx validation: passed.

### Increment 11.4 — PR #74

- GitHub Actions verification is pending for the current branch head.

### Increment 11.3 — merged PR #73

- SQLite: 233 passed, 7 skipped (500 assertions).
- PostgreSQL: 240 passed (583 assertions).
- Catalog entitlement, downgrade-preservation, and public-menu label tests: passed.
- Backblaze B2 S3 integration: passed in both database jobs.
- Frontend unit tests: 7 passed; Vue/TypeScript checks passed.
- Pint: 238 files passed; PHPStan reported no errors.
- Production image build, Compose validation, and Nginx validation: passed.
