# Phase 10 — Media and product image management

## Implemented on the Phase 10 branch

- Media metadata with ULID public identifiers, tenant ownership, detected MIME, file size, and pixel dimensions.
- Product-image relation with database composite foreign keys tying media, product, menu, and business ownership together.
- Dedicated private local disk at storage/app/media. The existing S3 disk can be selected with MEDIA_DISK=s3 after production bucket credentials and policy are configured.
- Server-generated filenames and tenant/product-scoped object paths.
- JPEG, PNG, and WebP validation; MIME and extension must agree; maximum upload size is 5 MiB; maximum width/height is 6000 pixels.
- Plan-snapshot quota enforcement: Economic 0, Professional 1, Top 5 images per product.
- Business and product row locks protect quota check plus metadata creation from concurrent uploads.
- Tenant-authorized upload, deletion, and image ordering endpoints.
- Public image delivery only when the business is active, the menu is published, and the product is published and available.
- Public-menu cache invalidation after image upload/deletion/reordering and product deletion cleanup.
- Catalog image upload/delete/order controls and public-menu first-image rendering.

## Required verification

The GitHub Actions jobs and owner-side local suite must pass. PostgreSQL is required to validate the row-lock quota behavior and composite foreign keys. SQLite-only success is insufficient.

## Remaining work

- Generate responsive variants (thumbnail / medium / original) and serve an appropriate srcset.
- Complete multi-image gallery presentation on the public menu.
- Verify S3-compatible storage against a real non-production bucket and document lifecycle/retention policy.
- Add a PostgreSQL multi-process concurrency test for simultaneous image uploads at the exact plan quota boundary.
- Validate file deletion recovery/cleanup for storage-provider failures.
- Run the full Release Gate separately; Phase 10 completion alone does not make the product production-ready.

## Security notes

- Image bytes are not stored on the public disk and no public storage symlink is used.
- The public URL is an opaque media ULID, not an authorization credential; every request independently checks publication and availability state.
- Client filenames are not used as paths.
- Product video remains out of scope for Phase 10.
