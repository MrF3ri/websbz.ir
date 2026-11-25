<?php

namespace App\Modules\PublicMenu\Services;

use App\Modules\Menu\Models\Menu;
use Closure;
use Illuminate\Support\Facades\Cache;

class PublicMenuCache
{
    private const TTL_MINUTES = 5;

    /** @param Closure(): mixed $resolver */
    public function remember(Menu $menu, string $locale, Closure $resolver): mixed
    {
        return Cache::remember(
            $this->key($menu, $locale),
            now()->addMinutes(self::TTL_MINUTES),
            $resolver,
        );
    }

    public function forget(Menu $menu): void
    {
        foreach (array_keys(config('locales.supported', ['fa' => []])) as $locale) {
            Cache::forget($this->key($menu, (string) $locale));
        }
    }

    private function key(Menu $menu, string $locale): string
    {
        return 'public-menu:v4:'.$menu->getKey().':'.$locale;
    }
}
