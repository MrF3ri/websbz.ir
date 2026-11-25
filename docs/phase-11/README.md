# Phase 11 — Public Menu Customization

Status: In progress  
Current increment: 11.11 — Tenant-session integration coverage  
Implementation branch: `feat/phase-11-tenant-integration-tests`

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

## Delivered in increment 11.4

- Add a subscription-snapshot-driven image slider to public product cards when more than one image exists.
- Keep the responsive image grid for Economic plans and products with one image.
- Add keyboard focus, previous/next controls, scroll-snap behavior, and accessible labels.
- Resolve the `image_slider` entitlement outside the cached public-menu payload so subscription changes take effect immediately.
- Version the public-menu cache key to prevent reuse of older payload shapes.
- Add coverage for entitlement changes while the public-menu catalog cache remains warm.
- Record the behavior in ADR 0018.

## Delivered in increment 11.5

- Add a 1200-character plain-text business introduction field to business settings.
- Gate new or changed introductions through the `business_introduction` subscription snapshot.
- Preserve existing text across downgrade, allow clearing it, and hide it from public menus while the feature is unavailable.
- Resolve the feature outside cached public-menu data so a downgrade takes effect immediately.
- Add tests for Professional/Economic access, downgrade behavior, and public rendering.
- Record the behavior in ADR 0019.

## Delivered in increment 11.6

- Add a fixed-field advertising banner to menu settings: title, message, CTA label, and HTTPS CTA URL.
- Gate new or changed banner content through the `advertising_banner` subscription snapshot.
- Preserve existing banners after downgrade, allow removal, and hide them from the public menu without entitlement.
- Render escaped plain text and a safe external CTA link; no arbitrary HTML, CSS, or JavaScript.
- Resolve the banner entitlement outside cached public-menu data so downgrades take effect immediately.
- Add tests for plan access, HTTPS validation, downgrade behavior, and public rendering.
- Record the decision in ADR 0020.

## Delivered in increment 11.7 — merged PR #77

- Add Top-only controlled design tokens for density, product card style, and heading size.
- Enforce `custom_design` from the current subscription snapshot on the server.
- Preserve existing custom design, color, font, and mode values after downgrade when unchanged.
- Permit resetting existing values to defaults without entitlement; deny assigning another non-default value.
- Render only fixed allow-listed tokens and application-owned class maps; no user CSS, HTML, or JavaScript.
- Add tests for Top access, Professional denial, downgrade preservation/reset, and public renderer fallbacks.
- Record the behavior in ADR 0021.

## Delivered in increment 11.8 — merged PR #78

- Extract the six allow-listed template preview styles into a typed frontend module.
- Give each template a distinct frame, header, and product-card treatment.
- Keep mobile and desktop preview widths explicit and deterministic.
- Add Vitest coverage for all six template IDs, distinct visual treatments, responsive widths, and unknown-template fallback.
- Improve preview control accessibility with group semantics, pressed states, and decorative preview hiding.

## Delivered in increment 11.9 — merged PR #79

- Add cache-invalidation regression tests for theme updates and business-logo upload/removal against a warm public-menu cache.
- Make logo removal transactional with object deletion: a storage failure rolls back the transaction and preserves the logo association and media metadata for retry.
- Verify failed logo replacement cleanup retains the old media metadata for retry.
- Document the storage cleanup contract in ADR 0016.

## Delivered in increment 11.10 — merged PR #80

- Add explicit accessible labels for the public menu landmark, template radio group, and advertising banner.
- Improve product image alternative text with image ordering and expose the slider as a named image gallery region.
- Announce the empty-menu state to assistive technology.
- Add a nested-route tenant-isolation regression test proving a business cannot resolve another business's menu.

## Delivered in increment 11.11 (implementation in progress)

- Add controller-level integration tests proving a forged foreign active-business session cannot redirect menu theme or advertising-banner updates to another tenant.
- Verify the tenant middleware restores the authenticated user's own active business before the settings controllers execute.

## Remaining Phase 11 work

1. Complete the remaining tenant-isolation and accessibility review for the public-menu customization flows.
2. Verify all new behavior across SQLite and PostgreSQL and retain the existing Release Gate requirements.

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

### Increment 11.3 — merged PR #73

- SQLite: 233 passed, 7 skipped (500 assertions).
- PostgreSQL: 240 passed (583 assertions).
- Catalog entitlement, downgrade-preservation, and public-menu label tests: passed.
- Backblaze B2 S3 integration: passed in both database jobs.
- Frontend unit tests: 7 passed; Vue/TypeScript checks passed.
- Pint: 238 files passed; PHPStan reported no errors.
- Production image build, Compose validation, and Nginx validation: passed.

### Increment 11.4 — merged PR #74

- SQLite: 234 passed, 7 skipped (522 assertions).
- PostgreSQL test suite: passed.
- PublicMenuTest image-slider entitlement/cache coverage: passed.
- Backblaze B2 S3 integration: passed in both database jobs.
- Frontend unit tests: 7 passed; Vue/TypeScript checks passed.
- Pint: 238 files passed; PHPStan reported no errors.
- Production image build, Compose validation, and Nginx validation: passed.

### Increment 11.5 — merged PR #75

- SQLite: 238 passed, 7 skipped (553 assertions).
- PostgreSQL: 245 passed (636 assertions).
- BusinessIntroductionTest, downgrade preservation, and public-menu entitlement tests: passed.
- Backblaze B2 S3 integration: passed in both database jobs.
- Frontend test files: 2 passed; Vue/TypeScript checks passed.
- PHPStan reported no errors; all CI quality checks passed.
- Production image build, Compose validation, and Nginx validation: passed.

### Increment 11.6 — merged PR #76

- SQLite: 244 passed, 7 skipped.
- PostgreSQL test suite: passed.
- MenuBannerTest: plan gating, downgrade preservation/removal, HTTPS validation, and public rendering passed.
- Backblaze B2 S3 integration: passed in both database jobs.
- Frontend test files: 2 passed; Vue/TypeScript checks passed.
- PHPStan reported no errors; all CI quality checks passed.
- Production image build, Compose validation, and Nginx validation: passed.

### Increment 11.7 — merged PR #77

- SQLite: 248 passed, 7 skipped.
- PostgreSQL: 255 passed.
- MenuThemeTest and Backblaze B2 S3 integration passed in both database jobs.
- Frontend unit tests, Vue/TypeScript checks, and PHPStan passed.
- Production image build, Compose validation, and Nginx validation passed.

### Increment 11.8 — merged PR #78

- SQLite: 248 passed, 7 skipped.
- PostgreSQL: 255 passed.
- Three template preview frontend regression tests passed; all frontend unit test files passed.
- Vue/TypeScript checks and PHPStan passed.
- Production image build, Compose validation, and Nginx validation passed.

### Increment 11.9 — merged PR #79

- SQLite: 252 passed, 7 skipped.
- PostgreSQL: 259 passed.
- BusinessLogoTest storage-failure and cache-invalidation coverage passed.
- MenuThemeCacheTest passed on both database engines.
- Backblaze B2 S3 integration passed.
- Frontend unit tests, Vue/TypeScript checks, Pint, and PHPStan passed.
- Production image build, Compose validation, and Nginx validation passed.

### Increment 11.10 — merged PR #80

- SQLite: 253 passed, 7 skipped.
- PostgreSQL: 260 passed.
- PublicMenuTenantIsolationTest passed on both database engines.
- Frontend unit tests: 3 files passed; Vue/TypeScript checks and PHPStan passed.
- Production image build, Compose validation, and Nginx validation passed.

### Increment 11.11 — current branch

- Tenant-session integration coverage for theme and banner settings is in progress; CI verification is pending.

- Accessibility improvements and nested tenant-route isolation coverage are in progress; CI verification is pending.
