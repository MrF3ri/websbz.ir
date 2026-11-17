<?php

namespace App\Modules\Product\Actions;

use App\Modules\Branch\Actions\SyncBranchCatalogAssignmentsAction;
use App\Modules\Business\Models\Business;
use App\Modules\Category\Models\Category;
use App\Modules\Menu\Models\Menu;
use App\Modules\Product\Models\Product;
use App\Modules\Subscription\Services\EnforcementService;
use App\Modules\Subscription\Services\EntitlementService;
use Illuminate\Support\Facades\DB;

class CreateProductAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(
        Business $business,
        Menu $menu,
        Category $category,
        array $data,
    ): Product {
        return DB::transaction(function () use (
            $business,
            $menu,
            $category,
            $data,
        ): Product {
            $lockedMenu = Menu::query()
                ->whereKey($menu->getKey())
                ->where('business_id', $business->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedCategory = $lockedMenu->categories()
                ->whereKey($category->getKey())
                ->firstOrFail();

            $enforcement = new EnforcementService(new EntitlementService($business));
            $enforcement->requireLimit(
                $business,
                'max_products',
                $lockedMenu->products()->count(),
            );

            if (($data['discount_price'] ?? null) !== null) {
                $enforcement->requireFeature($business, 'product_discount');
            }

            if (($data['is_available'] ?? true) === false) {
                $enforcement->requireFeature($business, 'product_availability_toggle');
            }

            $sortOrder = (int) ($lockedCategory->products()->max('sort_order') ?? -1) + 1;

            $product = $lockedCategory->products()->create([
                'menu_id' => $lockedMenu->getKey(),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'base_price' => $data['base_price'],
                'discount_price' => $data['discount_price'] ?? null,
                'is_available' => $data['is_available'] ?? true,
                'status' => $data['status'],
                'sort_order' => $sortOrder,
            ]);

            app(SyncBranchCatalogAssignmentsAction::class)
                ->product($business, $lockedMenu, $product);

            return $product;
        });
    }
}
