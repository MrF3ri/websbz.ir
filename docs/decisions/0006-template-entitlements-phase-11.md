# ADR 0006: Defer Template-Count Entitlements to Phase 11

## Context
Plan definitions include predefined template choices, but the entitlement foundation and early catalog phases do not implement menu customization.

## Options
- Add template-count feature keys during Baseline or Phase 8.
- Defer template-count entitlements until the template registry and selection workflow exist.
- Encode template availability as scattered plan-name checks.

## Chosen
Defer template-count entitlements to Phase 11, Menu Customization and Visual Templates.

## Reasoning
The entitlement key should be designed alongside the template registry and enforcement boundary. Adding it before that domain exists creates premature coupling.

## Consequences
Do not add a template-count feature key or enforcement in Baseline-A, Baseline-B, or Phase 8. Introduce and test it in Phase 11.
