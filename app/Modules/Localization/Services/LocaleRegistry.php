<?php

namespace App\Modules\Localization\Services;

use App\Modules\Business\Models\Business;
use App\Modules\Subscription\Services\EntitlementService;

class LocaleRegistry
{
    /**
     * @return array<string, array{name: string, native_name: string, direction: 'rtl'|'ltr'}>
     */
    public function supported(): array
    {
        /** @var array<string, array{name: string, native_name: string, direction: 'rtl'|'ltr'}> $locales */
        $locales = config('locales.supported', []);

        return $locales;
    }

    public function default(): string
    {
        $default = (string) config('locales.default', 'fa');

        return array_key_exists($default, $this->supported()) ? $default : 'fa';
    }

    /**
     * @return array<string, array{name: string, native_name: string, direction: 'rtl'|'ltr'}>
     */
    public function availableFor(Business $business): array
    {
        $supported = $this->supported();
        $limit = (new EntitlementService($business))->getLimit('max_languages');

        if ($limit === -1) {
            return $supported;
        }

        $count = max(1, min(count($supported), (int) ($limit ?? 1)));

        return array_slice($supported, 0, $count, true);
    }

    public function allows(Business $business, string $locale): bool
    {
        return array_key_exists($locale, $this->availableFor($business));
    }

    public function resolveFor(Business $business, string $defaultLocale, ?string $requestedLocale): string
    {
        $available = $this->availableFor($business);
        $fallback = array_key_exists($defaultLocale, $available)
            ? $defaultLocale
            : $this->default();

        if (! array_key_exists($fallback, $available)) {
            $fallback = (string) array_key_first($available);
        }

        return $requestedLocale !== null && array_key_exists($requestedLocale, $available)
            ? $requestedLocale
            : $fallback;
    }

    public function direction(string $locale): string
    {
        return $this->supported()[$locale]['direction'] ?? $this->supported()[$this->default()]['direction'] ?? 'rtl';
    }
}
