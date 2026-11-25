# Phase 10 — Completion status

This is an implementation checklist, not a production-release approval.

## Implemented

- Generate thumbnail (320px max edge) and medium (960px max edge) variants when they are smaller than the original; retain the uploaded original.
- Persist variant paths, dimensions, and MIME metadata with the media record.
- Expose each variant through the existing publication-checked media route.
- Return srcset/sizes metadata to the public menu and render every product image in a responsive gallery.
- Delete the original and all generated variants before deleting the final media metadata. If the storage provider reports a deletion failure, the transaction rolls back and metadata remains available for retry.
- Add regression tests for variant dimensions and delivery, multi-image gallery payload ordering, storage deletion failure, and PostgreSQL multi-process quota enforcement.
- Install and lock the S3 Flysystem adapter; add an opt-in real non-production S3 integration test and document private-bucket/lifecycle requirements.
- Wire optional GitHub Actions secrets for the real-bucket integration test.

## CI verification passed on merged PR #70 head `92e9007e1042da83729b6c3f278d36038f70bd83`

- SQLite suite and quality checks passed.
- PostgreSQL suite passed, including the multi-process quota-boundary test.
- Frontend unit/type checks, Pint, PHPStan, Composer/npm dependency audits, and production image/Compose validation passed.
- The real Backblaze B2 non-production S3 integration test executed and passed for upload, responsive variants, authorized delivery, and deletion.

Workflow runs:
- Tests: https://github.com/MrF3ri/websbz.ir/actions/runs/45651880192
- Production image: https://github.com/MrF3ri/websbz.ir/actions/runs/45651880103

## Still requires external verification

- Owner-side local acceptance of image upload, gallery rendering, and deletion remains required if the project checklist calls for local sign-off.
- Review/apply the documented S3 lifecycle policy to owner-managed buckets; do not expire current objects.

## Not a production-release approval

The full project Release Gate remains separate. Phase 10 completion does not certify production readiness, backup/restore, rollback, monitoring, or deployment.
