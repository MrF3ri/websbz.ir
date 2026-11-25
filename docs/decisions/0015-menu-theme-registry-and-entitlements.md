# ADR 0015: Menu Theme Registry and Snapshot-Based Template Entitlements

- Status: Accepted for Phase 11 implementation
- Date: 2026-11-25
- Related: ADR 0003, ADR 0006, ADR 0011, ADR 0014

## Context

Phase 11 introduces predefined menu templates and approved appearance settings. The application already snapshots plan features on subscription creation and resolves them through EntitlementService. Template availability was deliberately deferred until this phase.

## Decision

- Store the menu's presentation configuration in a nullable JSON `menus.theme` column. A null value means the renderer uses the backward-compatible default theme.
- Use a server-owned registry of six stable template identifiers. User input may select an identifier but may not supply HTML, CSS, JavaScript, component names, or arbitrary style declarations.
- Introduce the numeric `menu_template_count` feature in plan snapshots: Economic = 3, Professional = 6, Top = -1 (all registered templates). The registry remains the source of actual template metadata.
- Existing subscription snapshots remain immutable. New template entitlements apply to new subscriptions and explicit plan changes; do not silently rewrite existing snapshots.
- Template selection is enforced server-side against the current business's EntitlementService. Frontend visibility is not authorization.
- Custom Top-plan design remains a controlled configuration workflow; it does not permit arbitrary executable markup.

## Consequences

- Adding a template requires a stable registry entry and presentation test.
- A missing or unavailable entitlement fails closed.
- The public menu receives only a normalized, allow-listed theme payload.
- Theme changes must invalidate the public-menu cache.
