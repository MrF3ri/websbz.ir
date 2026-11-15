# ADR 0010: Catalog Authorization Roles

- Status: Accepted
- Date: 2026-11-15

## Context
Catalog administration is available to the business owner and designated staff roles. Platform administration is a separate actor and is not a BusinessRole.

## Decision
CategoryPolicy and ProductPolicy allow only owner, admin, manager, and editor memberships for the current business. All other users and roles are denied. Policies are the single authorization source; controllers invoke them once, while Form Requests perform validation only.

## Consequences
All catalog queries remain tenant-scoped independently of policy checks. Platform-admin privileges do not grant catalog access to a business unless the account also has an authorized business membership.
