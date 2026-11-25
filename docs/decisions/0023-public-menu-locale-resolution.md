# ADR 0023: Public menu locale resolution

- Status: Accepted for Phase 12 implementation
- Date: 2026-11-25

## Context

ADR 0022 defines the supported locales and normalized translation storage. The public menu already has a stable business/menu URL and a locale-neutral cache. Locale selection must not change tenant resolution, expose translations unavailable to a subscription, or cause one visitor's selected language to leak into another visitor's response.

## Decision

1. Keep the existing public menu path unchanged and select a language through the optional `lang` query parameter.
2. Resolve the requested locale on every request against the current business `max_languages` subscription snapshot. If the parameter is absent, malformed, unsupported, or unavailable, use the menu's `default_locale`.
3. Resolve locale direction and the language switcher options only from the server-side `LocaleRegistry`; never trust client-provided direction or locale lists.
4. Cache the locale-neutral source menu and translation records. Apply the requested locale after cache retrieval so locale selection does not fragment the cache or mix localized responses.
5. Transform menu title, category name, product name, and product description independently. A missing translation field falls back to its source-language value.
6. Return only the selected locale's text and the entitled locale metadata to the public page. Do not serialize all translation records to the browser.
7. Keep prices, discounts, availability, product labels, media, and branch overrides language-neutral.
8. Version the public-menu cache key when the cached payload schema changes.

## Consequences

- Existing QR codes and public menu URLs remain stable.
- A plan downgrade takes effect on the next request, even when the locale-neutral menu cache is warm.
- Translation fallback is deterministic and does not require placeholder translation rows.
- The public UI can set `lang` and `dir` from server-resolved metadata.
- Banner and business-introduction localization remains outside this increment and requires a separate approved data-model decision.
