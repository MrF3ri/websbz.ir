<?php

namespace App\Modules\PublicMenu\Services;

use App\Modules\Branch\Models\Branch;
use App\Modules\Menu\Models\Menu;
use Closure;
use Illuminate\Support\Facades\Cache;

class PublicMenuCache
{
    private const TTL_MINUTES = 5;

    /** @param Closure(): mixed $resolver */
    public function remember(Menu $menu, Closure $resolver, ?Branch $branch = null): mixed
    {
        return Cache::remember(
            $this->key($menu, $branch),
            now()->addMinutes(self::TTL_MINUTES),
            $resolver,
        );
    }

    public function forget(Menu $menu, ?Branch $branch = null): void
    {
        if ($branch !== null) {
            Cache::forget($this->key($menu, $branch));

            return;
        }

        Cache::forget($this->key($menu));

        Branch::query()
            ->where('business_id', $menu->business_id)
            ->get(['id'])
            ->each(function (Branch $branch) use ($menu): void {
                Cache::forget($this->key($menu, $branch));
            });
    }

    private function key(Menu $menu, ?Branch $branch = null): string
    {
        return 'public-menu:v6:'.$menu->getKey().':'.($branch?->getKey() ?? 'legacy');
    }
}
