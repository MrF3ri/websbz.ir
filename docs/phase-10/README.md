# Phase 10 — Media and product image management

Status: Implementation complete and merged through PRs #69 and #70. Owner-side acceptance and repository-wide Release Gate remain.

## Implemented on main

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

## Verification

- PR #69 delivered secure product image management and passed its configured SQLite/PostgreSQL/production-image checks.
- PR #70 delivered responsive variants, ordered multi-image galleries, storage deletion reliability, and PostgreSQL multi-process quota coverage.
- On PR #70 head `92e9007e1042da83729b6c3f278d36038f70bd83`, SQLite, PostgreSQL, frontend unit/type checks, Pint, PHPStan, dependency audits, and production image/Compose validation passed.
- The real Backblaze B2 non-production S3 integration test executed and passed on PR #70, covering upload, responsive variants, authorized delivery, and deletion. No production bucket or credentials were used.

## Remaining work

- Owner-side local acceptance of image upload, gallery rendering, and deletion remains a separate project sign-off.
- Review/apply the documented S3 lifecycle policy to owner-managed buckets; do not expire current objects that may still be referenced by media records.
- Run the full repository Release Gate separately; Phase 10 completion alone does not make the product production-ready.

## Security notes

- Image bytes are not stored on the public disk and no public storage symlink is used.
- The public URL is an opaque media ULID, not an authorization credential; every request independently checks publication and availability state.
- Client filenames are not used as paths.
- Product video remains out of scope for Phase 10.
