# ADR 0008: Jalali Date Presentation

- Status: Accepted
- Date: 2026-11-15

## Context
The product is Persian-first. Date values must remain interoperable and unambiguous in persistence and APIs while the interface uses the Persian calendar.

## Decision
Store timestamps as Gregorian UTC. Convert only at presentation boundaries. PHP formatting uses IntlDateFormatter with the Persian calendar and Asia/Tehran timezone. Vue uses Intl.DateTimeFormat with locale `fa-IR-u-ca-persian`, Asia/Tehran, and Persian digits. The three leap-year boundary vectors are mandatory regression tests.

## Consequences
No Jalali date string is stored in the database. ISO UTC values remain the transport contract; the client formats them for display.
