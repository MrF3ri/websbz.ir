# ADR 0012: Client-side QR generation for public menus

## Status

Accepted

## Context

Each published menu needs a downloadable QR code. Generating the code through a third-party HTTP API would disclose customer-facing URLs to an external service and add a runtime dependency.

## Decision

- Generate QR codes in the browser using the MIT-licensed `qrcode-generator` package.
- Encode the stable public menu URL and use medium error correction.
- Preview the QR code in catalog management and offer it as a scalable SVG download.
- Show the QR download only while the menu is published.
- Do not store QR images or send menu URLs to an external QR service.

## Consequences

- QR generation requires no server endpoint, storage, or background job.
- SVG remains sharp when printed at different sizes.
- The QR code always points to the canonical public menu URL; unpublishing the menu makes that URL unavailable until it is published again.
