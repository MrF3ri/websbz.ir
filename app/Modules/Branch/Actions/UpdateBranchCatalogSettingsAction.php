<?php

namespace App\Modules\Branch\Actions;

use App\Modules\Branch\Models\Branch;
use App\Modules\Branch\Models\BranchCategorySetting;
use App\Modules\Branch\Models\BranchProductSetting;
use App\Modules\Business\Models\Business;
use App\Modules\Category\Models\Category;
use App\Modules\Menu\Models\Menu;
use App\Modules\Product\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateBranchCatalogSettingsAction
{
    /**
     * @param  array{categories: array<int, array<string, mixed>>, products: array<int, array<string, mixed>>}  $data
     */
    public function execute(Business $business, Branch $branch, array $data): void
    {
        DB::transaction(function () use ($business, $branch, $data): void {
            $lockedBusiness = Business::query()
                ->whereKey($business->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedBranch = $lockedBusiness->branches()
                ->whereKey($branch->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $menu = Menu::query()
                ->where('business_id', $lockedBusiness->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $categoryIds = collect($data['categories'])->pluck('public_id');
            $productIds = collect($data['products'])->pluck('public_id');

            $categories = $menu->categories()
                ->whereIn('public_id', $categoryIds)
                ->get()
                ->keyBy('public_id');
            $products = $menu->products()
                ->whereIn('public_id', $productIds)
                ->get()
                ->keyBy('public_id');

            if ($categories->count() !== $categoryIds->unique()->count()
                || $products->count() !== $productIds->unique()->count()) {
                throw ValidationException::withMessages([
                    'catalog' => 'یک یا چند مورد انتخاب‌شده متعلق به این منو نیست.',
                ]);
            }

            foreach ($data['categories'] as $item) {
                /** @var Category $category */
                $category = $categories->get($item['public_id']);
                BranchCategorySetting::query()->updateOrCreate(
                    ['branch_id' => $lockedBranch->getKey(), 'category_id' => $category->getKey()],
                    [
                        'business_id' => $lockedBusiness->getKey(),
                        'menu_id' => $menu->getKey(),
                        'is_visible' => (bool) $item['is_visible'],
                        'sort_order' => (int) $item['sort_order'],
                    ],
                );
            }

            foreach ($data['products'] as $item) {
                /** @var Product $product */
                $product = $products->get($item['public_id']);
                $basePrice = $item['base_price_override'] ?? $product->base_price;
                $discountMode = $item['discount_override_mode'];
                $discountPrice = $discountMode === 'set'
                    ? ($item['discount_price_override'] ?? null)
                    : ($discountMode === 'clear' ? null : $product->discount_price);

                if ($discountMode === 'set' && $discountPrice === null) {
                    throw ValidationException::withMessages([
                        'products' => "برای تخفیف اختصاصی «{$product->name}» مبلغ تخفیف را وارد کنید.",
                    ]);
                }

                if ($discountPrice !== null && (int) $discountPrice >= (int) $basePrice) {
                    throw ValidationException::withMessages([
                        'products' => "قیمت تخفیف «{$product->name}» باید کمتر از قیمت پایه مؤثر باشد.",
                    ]);
                }

                if ((bool) $item['track_inventory'] && ($item['stock_quantity'] ?? null) === null) {
                    throw ValidationException::withMessages([
                        'products' => "برای موجودی‌شماری «{$product->name}» تعداد موجودی را وارد کنید.",
                    ]);
                }

                if (! (bool) $item['track_inventory'] && ($item['stock_quantity'] ?? null) !== null) {
                    throw ValidationException::withMessages([
                        'products' => "برای «{$product->name}» در حالت بدون موجودی‌شماری، تعداد موجودی باید خالی باشد.",
                    ]);
                }

                BranchProductSetting::query()->updateOrCreate(
                    ['branch_id' => $lockedBranch->getKey(), 'product_id' => $product->getKey()],
                    [
                        'business_id' => $lockedBusiness->getKey(),
                        'menu_id' => $menu->getKey(),
                        'name_override' => $item['name_override'] ?? null,
                        'description_override_enabled' => (bool) $item['description_override_enabled'],
                        'description_override' => $item['description_override'] ?? null,
                        'base_price_override' => $item['base_price_override'] ?? null,
                        'discount_override_mode' => $discountMode,
                        'discount_price_override' => $discountMode === 'set'
                            ? $item['discount_price_override']
                            : null,
                        'is_visible' => (bool) $item['is_visible'],
                        'is_available' => (bool) $item['is_available'],
                        'track_inventory' => (bool) $item['track_inventory'],
                        'stock_quantity' => $item['track_inventory'] ? (int) ($item['stock_quantity'] ?? 0) : null,
                        'sort_order' => (int) $item['sort_order'],
                    ],
                );
            }
        });
    }
}
