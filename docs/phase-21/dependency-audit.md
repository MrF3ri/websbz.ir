# Phase 21 — Dependency security audit

## CI policy

The SQLite/quality workflow audits the complete locked dependency sets after installation:

- `composer audit --locked` checks both production and development Composer packages against published security advisories.
- `npm audit --audit-level=high` checks both production and development npm packages and fails on high/critical advisories.

The audit runs on pull requests and pushes to `main`. This closes the gap left by production-only auditing: development tools execute in CI and can affect the integrity of builds and test results.

These checks do not replace license review, transitive dependency provenance review, or runtime image scanning.

## Response to findings

Do not suppress an advisory merely to make CI green. Identify the affected direct/transitive package, determine whether a compatible patched release exists, update the relevant manifest and lockfile through the package manager, and run the complete SQLite, PostgreSQL, and production-image checks. If no safe compatible update exists, document the exposure and mitigation explicitly.
