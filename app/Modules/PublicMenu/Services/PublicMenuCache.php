<?php

namespace App\Modules\PublicMenu\Services;

use App\Modules\Menu\Models\Menu;
use Closure;
use Illuminate\Support\Facades\Cache;

class PublicMenuCache
{
    private const TTL_MINUTES = 5;

    /** @param Closure(): mixed $resolver */
    public function remember(Menu $menu, Closure $resolver): mixed
    {
        return Cache::remember(
            $this->key($menu),
            now()->addMinutes(self::TTL_MINUTES),
            $resolver,
        );
    }

    public function forget(Menu $menu): void
    {
        Cache::forget($this->key($menu));
    }

    private function key(Menu $menu): string
    {
        return 'public-menu:'.$menu->getKey();
    }
}
