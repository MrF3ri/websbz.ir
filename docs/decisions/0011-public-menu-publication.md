# ADR 0011: Public menu publication boundary

## Status

Accepted

## Context

The catalog is private to authenticated business members. Customers must be able to browse a menu without an account, while drafts, unavailable products, inactive categories, and suspended businesses must remain hidden.

## Decision

- Public URLs use the stable business slug and primary menu slug: `/menu/{businessSlug}/{menuSlug}`.
- A public request resolves only an active business and a menu whose status is `published`.
- The public query returns active categories and only products that are both published and available.
- Publishing is an authenticated, tenant-scoped operation and requires at least one published, available product in an active category.
- Unpublishing returns the menu to draft and immediately makes the public URL return 404.
- The public page receives a deliberately limited presentation payload; internal IDs, membership data, subscription data, and draft content are not exposed.

## Consequences

- The public URL is stable and can later be encoded into a QR code.
- Menu publication is separate from individual product publication.
- Images, branch-specific overrides, custom domains, analytics, and QR generation remain later phases.
