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

## CI verification passed on PR head d37c5b4d

- SQLite: 214 passed, 8 skipped, 1,396 assertions.
- PostgreSQL: 221 passed, 1 skipped, 1,479 assertions. The multi-process quota-boundary test passed.
- Pint: 226 files passed.
- PHPStan: no errors.
- Frontend checks: 7 unit tests passed; type checks passed.
- Composer and npm dependency audits passed.
- Production image build and Compose/Nginx validation passed.

Workflow runs:
- Tests: https://github.com/MrF3ri/websbz.ir/actions/runs/45651880192
- Production image: https://github.com/MrF3ri/websbz.ir/actions/runs/45651880103

## Still requires external verification

- The S3 integration test was skipped because no non-production bucket credentials/opt-in secrets are configured. Run it against an owner-provisioned non-production bucket and apply the lifecycle policy in S3-VERIFICATION.md.
- Owner-side local acceptance of image upload, gallery rendering, and deletion remains required if the project checklist calls for local sign-off.

## Not a production-release approval

The full project Release Gate remains separate. Phase 10 completion does not certify production readiness, backup/restore, rollback, monitoring, or deployment.
