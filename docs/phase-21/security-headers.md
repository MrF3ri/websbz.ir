# Phase 21 — Baseline HTTP security headers

## Implemented slice

- `X-Content-Type-Options: nosniff` prevents MIME-type sniffing.
- `X-Frame-Options: DENY` prevents the application from being embedded in frames.
- `Referrer-Policy: strict-origin-when-cross-origin` limits referrer detail on cross-origin navigation.
- `Permissions-Policy` disables camera and microphone access because the current application has no browser capture workflow.
- `Strict-Transport-Security: max-age=31536000` is emitted only when Laravel considers the request secure, including correctly trusted forwarded-protocol requests.
- Middleware is global, so the application health endpoint receives the same baseline headers.

## Deliberate boundaries

- No Content Security Policy is emitted yet. A safe CSP needs an explicit nonce/hash strategy compatible with the current Vite/Inertia asset pipeline and any required external origins; adding a guessed policy could break the application or create a false sense of protection.
- HSTS does not include `includeSubDomains` or `preload`; those require a verified domain/subdomain HTTPS inventory and an explicit rollout decision.
- These headers complement, but do not replace, authorization, tenant isolation, output escaping, upload validation, and production HTTPS configuration.

## Verification

`SecurityHeadersTest` verifies the baseline headers on a normal web route and `/up`, and verifies HSTS is absent on HTTP but present on a secure request.