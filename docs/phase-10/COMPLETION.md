# Phase 10 — Completion status

This is an implementation checklist, not a release approval.

## Implemented in this completion branch; CI verification pending

- Generate thumbnail (320px max edge) and medium (960px max edge) variants when they are smaller than the original; retain the uploaded original.
- Persist variant paths, dimensions, and MIME metadata with the media record.
- Expose each variant through the existing publication-checked media route.
- Return srcset/sizes metadata to the public menu and render every product image in a responsive gallery.
- Delete the original and all generated variants before deleting the final media metadata. If the storage provider reports a deletion failure, the transaction rolls back and metadata remains available for retry.
- Add regression tests for variant dimensions and delivery, multi-image gallery payload ordering, storage deletion failure, and PostgreSQL multi-process quota enforcement.
- Add an opt-in integration test for a real non-production S3-compatible bucket.

## Still requires external verification

- The PostgreSQL multi-process concurrency test must run and pass in GitHub Actions.
- The opt-in S3 integration test must run against an owner-provisioned non-production bucket. A fake disk is not evidence of provider compatibility.
- The bucket must be private and use the lifecycle policy in S3-VERIFICATION.md.
- All SQLite, PostgreSQL, frontend, static-analysis, dependency-audit, and production-image checks must pass on the final PR head.

## Not a Phase 10 completion or production-release approval

The full project Release Gate remains separate. Phase 10 being complete does not certify production readiness, backup/restore, rollback, monitoring, or deployment.
