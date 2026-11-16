# Phase 21 — Production dependency audit

## CI policy

The SQLite/quality workflow now audits production dependencies after installing the locked dependency sets:

- `composer audit --locked --no-dev` checks the Composer lockfile's production packages against published security advisories.
- `npm audit --omit=dev --audit-level=high` checks npm production dependencies and fails on high/critical advisories.

These checks are intended to run on pull requests and pushes to `main`. They do not replace periodic review of development-only tooling, license review, transitive dependency provenance, or runtime image scanning.

## Response to findings

Do not suppress an advisory merely to make CI green. Identify the affected direct/transitive package, determine whether a compatible patched release exists, update the relevant lockfile through the package manager, and run the complete SQLite, PostgreSQL, and production-image checks. If no safe compatible update exists, document the exposure and mitigation explicitly.