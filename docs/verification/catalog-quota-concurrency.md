# Manual Verification: Concurrent Catalog Quota Enforcement

The catalog create paths serialize quota checks by locking the owning menu row with `SELECT ... FOR UPDATE`, then counting the current database rows and invoking EnforcementService before insertion.

A deterministic parallel HTTP race is not suitable for the current in-process PHPUnit setup: a second connection blocks on the first connection's row lock, while the same test process cannot commit the first transaction until the blocked call returns. The following manual check exercises the real authenticated HTTP endpoint with two independent sessions.

## Preconditions

- Use a non-production PostgreSQL database.
- Create an Economic-plan business with an active subscription.
- Ensure its primary menu has exactly 19 products and at least one category.
- Open two independent browser sessions authenticated as the same business owner.
- Keep the same category selected in both sessions.

## Procedure

1. In both sessions, open the catalog product form.
2. Prepare two distinct products with valid names and prices.
3. Submit both forms as close together as possible.
4. Record both HTTP outcomes in the browser Network panel.
5. Query the product count for that menu.

## Expected result

- Exactly one request succeeds.
- The other request receives HTTP 403 because the quota is now reached.
- The menu contains exactly 20 products; it must never contain 21.
- The successful product belongs to the selected menu and category.
- No partial product row is persisted for the rejected request.

Repeat with Professional at five categories and submit two concurrent category creations; exactly one may consume the final slot.
