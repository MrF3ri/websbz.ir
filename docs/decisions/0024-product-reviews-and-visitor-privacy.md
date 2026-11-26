# ADR 0024 — Product reviews, moderation, and visitor privacy

- Status: Proposed for Phase 15.2 implementation
- Date: 2026-11-26

## Context

Top-plan businesses may collect product ratings and reviews from visitors of their public menus. Public submissions must not become visible before the business has reviewed them. The product catalog is shared across branches, so a product review belongs to the canonical product rather than an individual branch.

The public menu does not require customer accounts. Collecting email addresses, phone numbers, or persistent personal identity would add unnecessary privacy and account-management scope.

## Decision

### Submission and visitor identity

- Only businesses whose active subscription snapshot enables product_reviews may accept or display reviews.
- A visitor may submit one review per product per browser identity.
- On the first successful submission, the application creates a random 256-bit browser token and sets it in an HttpOnly, SameSite=Lax cookie; Secure is enabled for HTTPS requests.
- Laravel's cookie middleware encrypts the cookie in transit. The database stores only the SHA-256 hash, never the raw token.
- The token is pseudonymous and resettable. It is duplicate prevention, not proof of a real-world customer identity.
- Do not persist visitor IP addresses, email addresses, or phone numbers for this feature.
- Apply a route-level rate limit of five submissions per minute per source IP. The product row lock and unique (product_id, visitor_key_hash) constraint serialize and prevent duplicate submissions under concurrency.

### Review content and moderation

- Rating is required and must be an integer from 1 through 5.
- Display name is optional and limited to 80 characters.
- Review text is optional and limited to 1,500 characters; rating-only feedback is allowed.
- Every new review starts in pending.
- Only authorized business catalog members (Owner, Admin, Manager, Editor) may approve or reject reviews for their current business.
- A review is publicly visible only after approval. Rejected and pending reviews are never included in the public projection.
- Moderation is a one-way transition from Pending to Approved or Rejected in this increment.
- Public aggregates (count and average) include approved reviews only.
- Show up to the three most recent approved reviews on the public menu.

### Branch behavior

Reviews are product-wide and shared across branches because the product catalog is canonical and branch settings override presentation/availability rather than creating separate products. A submission must still come through an active branch URL and the canonical menu/product must be published and available.

### Data isolation and lifecycle

- Store business, menu, and product ownership keys and enforce same-menu/same-business relationships with composite foreign keys.
- Cascade review deletion with its canonical product/menu/business.
- Keep moderation actor and timestamp for accountability.
- Never include visitor token hashes or moderation metadata in public responses.

## Consequences

- No customer login or contact information is required.
- Browser identity can be reset by clearing cookies or using another browser/device; rate limiting and moderation remain necessary.
- Manual moderation prevents immediate public spam but requires the business to review pending submissions.
- Reviews do not have branch-specific ratings.
- Customer editing/deletion, owner replies, verified-purchase status, report/flag workflow, and automated spam scoring are outside this increment.

## Verification requirements

- Top-plan submission succeeds and remains pending.
- Non-Top submission and moderation endpoints are unavailable.
- Invalid ratings and oversized text are rejected.
- Duplicate submissions with the same browser token are rejected, including concurrent requests.
- Tenant-crossing moderation is rejected.
- Public output contains only approved reviews and approved-only aggregates.
- Approval invalidates the relevant public-menu cache; rejection does not expose the review.
- SQLite and PostgreSQL suites plus production-image validation pass.
