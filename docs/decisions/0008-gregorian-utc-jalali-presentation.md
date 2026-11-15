# ADR 0008: Gregorian UTC Dates and Jalali Presentation Edge

## Context
The dashboard is Persian-first, but persisted dates, APIs, Inertia props, and date logic must be timezone-stable. ERRATA 12 requires Jalali only for user-facing presentation in Asia/Tehran.

## Options
- Persist and exchange Jalali strings.
- Convert dates ad hoc in each page/controller.
- Use a date-conversion package as central implementation.
- Use native `Intl` in Vue and PHP `intl` calendar support behind central helpers.

## Chosen
Store dates and expose API/Inertia date values as Gregorian ISO 8601 UTC. Convert to Jalali only at presentation edge through one central Vue composable using `Intl.DateTimeFormat` with `fa-IR-u-ca-persian` and one central PHP helper using `intl` Persian calendar. Display in Asia/Tehran. Jalali date pickers submit Gregorian ISO values; server validates Gregorian input only.

## Reasoning
Native Intl/PHP intl avoids another date package and uses platform internationalization support. Central helpers prevent inconsistent conversion and keep domain logic calendar-independent.

## Consequences
PHP runtime must include `intl`. Tests cover leap years, including Jalali 1403 having Esfand 30 and 1404 not having Esfand 30. APIs, DB values, Inertia props, and date calculations must never become Jalali strings. Vue composable and PHP helper are Baseline-B work, not part of this docs/infrastructure-only task.
