# ADR 0003: Scoped Session Tenant Context

- Status: Accepted
- Date: 2026-11-15

## Context
A user can belong to multiple businesses. The initial UI uses a session-selected active business.

## Decision
Register CurrentBusiness with Laravel's scoped lifecycle. A valid active_business_id session selection is accepted only when the authenticated user is a member. A stale or foreign selection is removed. If no valid selection exists, choose the lowest business internal ID deterministically.

## Consequences
Requests and jobs must not share tenant state. A new scope starts empty unless explicitly initialized. ULIDs are not used as an authorization shortcut.
