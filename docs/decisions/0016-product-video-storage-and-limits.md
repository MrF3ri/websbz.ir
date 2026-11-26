# ADR 0016: Product video storage and limits

- Status: Accepted
- Date: 2026-11-26

## Context

Product videos are a Top-plan feature. The first release should support short, lightweight clips without introducing a transcoding worker or a separate video platform.

## Decision

- Allow one video per product.
- Top entitlement enables `product_video` and sets `max_product_videos = 1`; other plans set it to false/zero.
- Accept MP4 only, with a maximum size of 10 MiB and a maximum duration of 5 seconds.
- The business owner is responsible for compressing the video before upload. The server validates the actual file type, MP4 duration metadata, file size, tenant ownership, and entitlement.
- Store the original MP4 in the configured private media disk. Public delivery is available only while the product and menu are published, the product is available, and the business subscription enables video.
- Do not transcode or generate alternate renditions in this increment. Keep the storage model extensible for future background processing and adaptive streaming.
- Delete the stored object when the product video is removed. Database and object-storage deletion must be reviewed together.

## Consequences

- No FFmpeg or transcoding infrastructure is required for the first release.
- MP4-only keeps duration parsing and browser playback predictable.
- The upload endpoint remains authoritative; client-side duration checks are only a user-experience aid.
- A future transcoding pipeline can add derived media variants without changing product-video ownership.
