<?php

namespace App\Modules\Category\Actions;

use App\Modules\Branch\Actions\SyncBranchCatalogAssignmentsAction;
use App\Modules\Business\Models\Business;
use App\Modules\Category\Models\Category;
use App\Modules\Menu\Models\Menu;
use App\Modules\Subscription\Services\EnforcementService;
use App\Modules\Subscription\Services\EntitlementService;
use Illuminate\Support\Facades\DB;

class CreateCategoryAction
{
    public function execute(
        Business $business,
        Menu $menu,
        string $name,
        bool $isActive = true,
    ): Category {
        return DB::transaction(function () use (
            $business,
            $menu,
            $name,
            $isActive,
        ): Category {
            $lockedMenu = Menu::query()
                ->whereKey($menu->getKey())
                ->where('business_id', $business->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $currentCount = $lockedMenu->categories()->count();

            (new EnforcementService(new EntitlementService($business)))
                ->requireLimit($business, 'max_categories', $currentCount);

            $sortOrder = (int) ($lockedMenu->categories()->max('sort_order') ?? -1) + 1;

            $category = $lockedMenu->categories()->create([
                'name' => $name,
                'is_active' => $isActive,
                'sort_order' => $sortOrder,
            ]);

            app(SyncBranchCatalogAssignmentsAction::class)
                ->category($business, $lockedMenu, $category);

            return $category;
        });
    }
}
