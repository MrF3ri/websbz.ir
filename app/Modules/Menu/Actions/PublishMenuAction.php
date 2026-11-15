<?php

namespace App\Modules\Menu\Actions;

use App\Modules\Menu\Enums\MenuStatus;
use App\Modules\Menu\Models\Menu;
use App\Modules\Product\Enums\ProductStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublishMenuAction
{
    public function execute(Menu $menu): Menu
    {
        return DB::transaction(function () use ($menu): Menu {
            $lockedMenu = Menu::query()
                ->whereKey($menu->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $hasPublicProducts = $lockedMenu->categories()
                ->where('is_active', true)
                ->whereHas('products', function ($query): void {
                    $query
                        ->where('status', ProductStatus::Published)
                        ->where('is_available', true);
                })
                ->exists();

            if (! $hasPublicProducts) {
                throw ValidationException::withMessages([
                    'menu' => 'برای انتشار منو، دست‌کم یک محصول منتشرشده و موجود در یک دسته‌بندی فعال لازم است.',
                ]);
            }

            $lockedMenu->update(['status' => MenuStatus::Published]);

            return $lockedMenu->refresh();
        }, attempts: 3);
    }
}
