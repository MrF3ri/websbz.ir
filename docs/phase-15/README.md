# Phase 15 — Top-plan advanced features

## Increment 15.1 — Product video

Status: Product-video upload and public playback implementation prepared in PR.

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
