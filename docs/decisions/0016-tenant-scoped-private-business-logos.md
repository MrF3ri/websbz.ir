# ADR 0016: Tenant-Scoped Private Business Logos

- Status: Accepted for Phase 11 implementation
- Date: 2026-11-25
- Related: ADR 0015, Phase 10 media storage

## Context

Business logos are public-facing brand assets, but their source objects must remain in the private media storage provider. A logo must never be exposed through a public storage disk or an unscoped object URL. The business-logo feature is available only when the active subscription snapshot enables `business_logo`.

## Decision

- Store logo bytes through the configured private media disk (`MEDIA_DISK`, default `media`) and keep the file metadata in the existing `media` table.
- Represent the single current logo through a dedicated `business_logos` relation table. Enforce one logo per business and one logo record per media record.
- Enforce tenant ownership at the database boundary with a composite foreign key from `business_logos(media_id, business_id)` to `media(id, business_id)`.
- Upload authorization is checked against the current tenant's BusinessPolicy and the current subscription snapshot. UI visibility is not authorization.
- Accept only JPEG, PNG, and WebP images, maximum 2 MiB and 2048 × 2048 pixels; verify actual MIME type and extension before storage.
- Serve a logo only through an application route that verifies the business is active, its menu is published, and the requested media belongs to that business's current logo record.
- Replace or remove the logo by updating the relation transactionally, then clean up the old private object and metadata. Storage cleanup failures are logged and metadata is retained when possible.
- Invalidate the public-menu cache after upload or removal. Public URLs include the media public ID so replacement changes the URL.

## Consequences

- Private storage remains private; no storage URL is exposed to the browser.
- Public delivery is restricted to active businesses with a published menu.
- A database constraint prevents cross-business logo/media associations even if application validation regresses.
- A plan downgrade does not silently delete an existing logo; it prevents new uploads while retaining the current public asset until the owner removes or replaces it.
