# Phase 15 — Top-plan advanced features

## Increment 15.1 — Product video (complete)

Status: Increment 15.1 (product video) and 15.2 (product reviews and ratings) are implemented, merged, and verified on `main` through PRs #97 and #99.

### Approved first-release limits

- Top plan only.
- One MP4 video per product.
- Maximum file size: 10 MiB (10,485,760 bytes).
- Maximum duration: 5 seconds.
- The business owner compresses the video before upload.
- The server validates the actual MIME type, extension, MP4 duration metadata, file size, tenant ownership, and plan entitlement.
- Original video is stored on the configured private media disk.
- Public playback is exposed only for a published menu, published and available product, active business, and active video entitlement.
- No server-side transcoding or alternate renditions in this increment.

### Deferred

- FFmpeg/transcoding and adaptive HLS/DASH.
- Video thumbnail generation.
- Multiple videos per product.
- External video platforms.

See ADR 0016 for the decision record.


### Verification and delivery

- PR: [#97 — feat(media): add Top-plan short product videos](https://github.com/MrF3ri/websbz.ir/pull/97).
- Merge commit: `410652edbcf9ef27f47098fab29e8a146b8245b1`.
- Exact PR head: `758f7c9f7a33f196cf27acec77d1525e3be08cbf`.
- Pre-merge SQLite/quality, PostgreSQL, production-image, Composer audit, and NPM audit checks passed.
- Post-merge SQLite/quality, PostgreSQL, and production-image workflows passed.
- This completion applies only to increment 15.1; it does not complete Phase 15.
 
## Increment 15.2 — Product reviews and ratings (complete)

- Top-plan only, based on the subscription feature snapshot.
- Guest submissions require a 1–5 rating; display name and review text are optional.
- All submissions start pending and require an authorized business catalog member to approve or reject.
- Only approved reviews and approved-only aggregates appear publicly.
- One review per product per pseudonymous browser token; the raw token is held only in an encrypted HttpOnly cookie and only its SHA-256 hash is stored in the database.
- No email, phone number, or raw IP address is persisted for review identity.
- Reviews are product-wide across branches because branches share canonical products.
- Public submission requires an active branch URL, active business, published menu, active category, and a product that is published, available, and visible/available in that branch.
- PR #99 merged at commit `6880c59c7894873d946e635e1eca8565817acef9`.
- Exact-head pre-merge and post-merge CI passed: SQLite 308 passed (2 skipped), PostgreSQL 320 passed, Production Image/Compose/Nginx passed; Pint, PHPStan, TypeScript, Composer audit, and NPM audit passed.

See ADR 0024 for the accepted privacy, moderation, and abuse-prevention decision.

## Increment 15.5 — Product export (implementation in progress)

- Top-plan only through the excel_export entitlement.
- Export the current business's canonical menu products only.
- Stream an Excel-compatible UTF-8 CSV with BOM in chunks of 500; do not create temporary files or queue a job.
- Neutralize formula-leading cells and send no-store/nosniff headers.
- The output is CSV, not a native XLSX workbook. Branch-specific overrides and private media paths are excluded.
- See ADR 0025 for the format and streaming decision.

## Remaining Phase 15 increments

- **15.3 — Product likes:** like/unlike, anonymous/session identity, duplicate prevention, abuse protection, and entitlement. Define the identity/privacy model before implementation.
- **15.4 — Instagram integration:** official supported API only, OAuth/token lifecycle, retrieval/display, disconnect, and API-limit handling. Verify current API capabilities before implementation; no scraping.
- **15.6 — Backup and restore:** scope, schedule, encryption, secure retention, and a tested restore procedure.
- **15.7 — Notifications and customer communication:** notification model, delivery tracking, preferences, failure handling, queue processing, rate limits, consent, and privacy.
- **15.8 — Initial setup service:** setup status, assigned operator, required information, completion time, internal notes, and audit trail.
