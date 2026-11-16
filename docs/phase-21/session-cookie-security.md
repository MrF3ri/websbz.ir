# Phase 21 — Session cookie security

Production sessions use the database driver, encrypted session payloads, `HttpOnly` cookies, `SameSite=Lax`, and the `Secure` flag. The production Compose service and `.env.production.example` explicitly set these values.

The application config now also defaults the session cookie to `Secure` whenever `APP_ENV=production`, unless an explicit `SESSION_SECURE_COOKIE` override is supplied. Local/testing environments retain their non-HTTPS-compatible default.

The feature suite verifies that Laravel emits the session cookie with Secure, HttpOnly, and SameSite=Lax flags when configured. Production deployment must still use HTTPS and set trusted proxy headers correctly; the cookie flag is not a substitute for TLS.
