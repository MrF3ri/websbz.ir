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
- Catalog image upload/delete/order controls.
- Generated thumbnail and medium variants (where smaller than the original) plus original-image delivery through a responsive srcset.
- Public-menu multi-image gallery rendering for all images attached to a published product.

## Required verification

The GitHub Actions jobs and owner-side local suite must pass. PostgreSQL is required to validate the row-lock quota behavior and composite foreign keys. SQLite-only success is insufficient.

## Remaining work

- Run the new responsive-variant, gallery, storage-failure, and PostgreSQL multi-process concurrency tests in CI; implementation is not considered verified until the runs pass.
- Add and lock the missing league/flysystem-aws-s3-v3 Composer adapter, then run the opt-in S3 integration test against a real non-production bucket and apply the lifecycle policy in S3-VERIFICATION.md. This also requires owner-provisioned credentials and endpoint configuration; it cannot be certified using a fake disk.
- Run the full Release Gate separately; Phase 10 completion alone does not make the product production-ready.

## Security notes

- Image bytes are not stored on the public disk and no public storage symlink is used.
- The public URL is an opaque media ULID, not an authorization credential; every request independently checks publication and availability state.
- Client filenames are not used as paths.
- Product video remains out of scope for Phase 10.
