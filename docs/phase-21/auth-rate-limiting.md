# Phase 21 — Authentication and billing rate limits

## Configured limits

- Login attempts: five per minute, keyed by normalized username and client IP.
- Two-factor challenge attempts: five per minute, keyed by the pending login user ID.
- Passkey operations: ten per minute, keyed by credential ID (or session ID when no credential ID is supplied) and client IP.
- Password-reset link generation: a 60-second per-user broker throttle.
- Manual payment submissions: five requests per minute per authenticated route identity.

The login, two-factor, password-reset, and payment limits are covered by feature tests. The passkey limiter is configured in Fortify and should be exercised by the passkey feature suite when that workflow is expanded.

## Operational note

Rate-limit correctness depends on a shared cache backend in multi-instance production. Do not use an in-memory or per-container cache store when horizontally scaling the application; configure a shared Redis/database-backed limiter store and verify proxy IP forwarding so client IP keys cannot be spoofed.
