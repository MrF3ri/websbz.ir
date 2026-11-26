# Phase 15 — Top-plan advanced features

## Increment 15.1 — Product video (complete)

Status: Increment 15.1 — Product video — implemented, merged, and verified on `main` through PR #97 (merge commit `410652edbcf9ef27f47098fab29e8a146b8245b1`).

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
 
## Increment 15.2 — Product reviews and ratings (implementation in progress)

- Top-plan only, based on the subscription feature snapshot.
- Guest submissions require a 1–5 rating; display name and review text are optional.
- All submissions start pending and require an authorized business catalog member to approve or reject.
- Only approved reviews and approved-only aggregates appear publicly.
- One review per product per pseudonymous browser token; the raw token is held only in an encrypted HttpOnly cookie and only its SHA-256 hash is stored in the database.
- No email, phone number, or raw IP address is persisted for review identity.
- Reviews are product-wide across branches because branches share canonical products.
- Public submission requires an active branch URL, active business, published menu, and published/available product.

See ADR 0024 for the privacy, moderation, and abuse-prevention decision.

## Remaining Phase 15 increments

- **15.2 — Product reviews and ratings:** implementation in progress in this PR, with submission, rating validation, pending moderation, spam prevention, public display, tenant isolation, and Top-plan entitlement. See ADR 0024.
- **15.3 — Product likes:** like/unlike, anonymous/session identity, duplicate prevention, abuse protection, and entitlement. Define the identity/privacy model before implementation.
- **15.4 — Instagram integration:** official supported API only, OAuth/token lifecycle, retrieval/display, disconnect, and API-limit handling. Verify current API capabilities before implementation; no scraping.
- **15.5 — Excel export:** tenant-scoped product export, plan access, safe downloadable files, and queued processing where needed.
- **15.6 — Backup and restore:** scope, schedule, encryption, secure retention, and a tested restore procedure.
- **15.7 — Notifications and customer communication:** notification model, delivery tracking, preferences, failure handling, queue processing, rate limits, consent, and privacy.
- **15.8 — Initial setup service:** setup status, assigned operator, required information, completion time, internal notes, and audit trail.
