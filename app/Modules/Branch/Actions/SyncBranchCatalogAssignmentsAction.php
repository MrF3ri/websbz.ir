<?php

namespace App\Modules\Branch\Actions;

use App\Modules\Branch\Models\Branch;
use App\Modules\Branch\Models\BranchCategorySetting;
use App\Modules\Branch\Models\BranchProductSetting;
use App\Modules\Business\Models\Business;
use App\Modules\Category\Models\Category;
use App\Modules\Menu\Models\Menu;
use App\Modules\Product\Models\Product;
use LogicException;

class SyncBranchCatalogAssignmentsAction
{
    public function initialize(Branch $branch): void
    {
        $menu = Menu::query()
            ->where('business_id', $branch->business_id)
            ->first();

        if ($menu === null) {
            return;
        }

        foreach ($menu->categories()->orderBy('id')->get(['id', 'sort_order']) as $category) {
            BranchCategorySetting::query()->firstOrCreate(
                [
                    'branch_id' => $branch->getKey(),
                    'category_id' => $category->getKey(),
                ],
                [
                    'business_id' => $branch->business_id,
                    'menu_id' => $menu->getKey(),
                    'is_visible' => true,
                    'sort_order' => $category->sort_order,
                ],
            );
        }

        foreach ($menu->products()->orderBy('id')->get(['id', 'is_available', 'sort_order']) as $product) {
            BranchProductSetting::query()->firstOrCreate(
                [
                    'branch_id' => $branch->getKey(),
                    'product_id' => $product->getKey(),
                ],
                [
                    'business_id' => $branch->business_id,
                    'menu_id' => $menu->getKey(),
                    'description_override_enabled' => false,
                    'discount_override_mode' => 'inherit',
                    'is_visible' => true,
                    'is_available' => $product->is_available,
                    'track_inventory' => false,
                    'stock_quantity' => null,
                    'sort_order' => $product->sort_order,
                ],
            );
        }
    }

    public function category(Business $business, Menu $menu, Category $category): void
    {
        $this->assertCatalogOwnership($business, $menu);

        if ((int) $category->menu_id !== (int) $menu->getKey()) {
            throw new LogicException('The category does not belong to the supplied menu.');
        }

        $branches = Branch::query()
            ->where('business_id', $business->getKey())
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        foreach ($branches as $branch) {
            BranchCategorySetting::query()->firstOrCreate(
                [
                    'branch_id' => $branch->getKey(),
                    'category_id' => $category->getKey(),
                ],
                [
                    'business_id' => $business->getKey(),
                    'menu_id' => $menu->getKey(),
                    'is_visible' => true,
                    'sort_order' => $category->sort_order,
                ],
            );
        }
    }

    public function product(Business $business, Menu $menu, Product $product): void
    {
        $this->assertCatalogOwnership($business, $menu);

        if ((int) $product->menu_id !== (int) $menu->getKey()) {
            throw new LogicException('The product does not belong to the supplied menu.');
        }

        $branches = Branch::query()
            ->where('business_id', $business->getKey())
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        foreach ($branches as $branch) {
            BranchProductSetting::query()->firstOrCreate(
                [
                    'branch_id' => $branch->getKey(),
                    'product_id' => $product->getKey(),
                ],
                [
                    'business_id' => $business->getKey(),
                    'menu_id' => $menu->getKey(),
                    'description_override_enabled' => false,
                    'discount_override_mode' => 'inherit',
                    'is_visible' => true,
                    'is_available' => $product->is_available,
                    'track_inventory' => false,
                    'stock_quantity' => null,
                    'sort_order' => $product->sort_order,
                ],
            );
        }
    }

    private function assertCatalogOwnership(Business $business, Menu $menu): void
    {
        if ((int) $menu->business_id !== (int) $business->getKey()) {
            throw new LogicException('The menu does not belong to the supplied business.');
        }
    }
}
