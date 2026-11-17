<?php

namespace App\Modules\Branch\Actions;

use App\Modules\Branch\Models\Branch;
use App\Modules\Branch\Models\BranchCategorySetting;
use App\Modules\Branch\Models\BranchProductSetting;
use App\Modules\Business\Models\Business;
use Illuminate\Support\Facades\DB;

class EnsurePrimaryBranchAction
{
    public function execute(Business $business): Branch
    {
        return DB::transaction(function () use ($business): Branch {
            $lockedBusiness = Business::query()
                ->whereKey($business->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $branch = Branch::query()
                ->where('business_id', $lockedBusiness->getKey())
                ->where('is_primary', true)
                ->first();

            if (! $branch instanceof Branch) {
                $branch = Branch::query()->create([
                    'business_id' => $lockedBusiness->getKey(),
                    'name' => $lockedBusiness->name,
                    'slug' => 'main',
                    'status' => 'active',
                    'is_primary' => true,
                    'primary_business_id' => $lockedBusiness->getKey(),
                    'timezone' => 'Asia/Tehran',
                ]);
            }

            $menu = $lockedBusiness->menu()->first();

            if ($menu === null) {
                return $branch;
            }

            foreach ($menu->categories()->orderBy('id')->get(['id', 'sort_order']) as $category) {
                BranchCategorySetting::query()->firstOrCreate(
                    [
                        'branch_id' => $branch->getKey(),
                        'category_id' => $category->getKey(),
                    ],
                    [
                        'business_id' => $lockedBusiness->getKey(),
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
                        'business_id' => $lockedBusiness->getKey(),
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

            return $branch;
        });
    }
}
