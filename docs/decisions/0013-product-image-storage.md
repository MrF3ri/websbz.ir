# ADR 0013 — Product image storage and ownership

- Status: Accepted for Phase 10 implementation
- Date: 2026-11-25

## Context

Product images are tenant-owned assets. They must remain outside the public web root, be quota-limited by the subscription snapshot, and never be attachable across businesses.

## Decision

- Store file bytes on a dedicated private filesystem disk. Local development defaults to storage/app/media; production can select the existing S3-compatible disk using MEDIA_DISK=s3.
- Store only metadata in PostgreSQL: business ownership, disk, generated path, detected MIME, byte size, and image dimensions.
- Generate opaque ULID filenames server-side. Do not use client-provided names as storage paths.
- Link images to products through product_images; composite foreign keys enforce that the menu, product, and media all belong to the same business.
- Serialize public image URLs only for published, available products in published menus belonging to active businesses. Image delivery independently checks the same publication boundary.
- Enforce max_product_images while holding the business and product rows in a transaction, so concurrent uploads cannot exceed the plan quota.
- Accept JPEG, PNG, and WebP only; enforce a 5 MiB upload limit and a maximum dimension of 6000 × 6000 pixels.
## Consequences

- Private local storage is safe for development and no storage:link is required for media.
- Production S3 credentials and bucket policy remain deployment configuration, not repository secrets.
- Deleting a product image removes its metadata and stored object when no other product-image relation references it.
- Product video storage remains out of scope for Phase 10.
