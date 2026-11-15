<?php

namespace App\Modules\Product\Http\Controllers;

use App\Modules\Business\Models\Business;
use App\Modules\Category\Models\Category;
use App\Modules\Menu\Actions\EnsurePrimaryMenuAction;
use App\Modules\Menu\Models\Menu;
use App\Modules\Product\Http\Requests\ReorderProductsRequest;
use App\Modules\Product\Http\Requests\SaveProductRequest;
use App\Modules\Product\Models\Product;
use App\Modules\Subscription\Services\EnforcementService;
use App\Modules\Subscription\Services\EntitlementService;
use App\Modules\Tenancy\Services\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ProductController
{
    public function store(
        SaveProductRequest $request,
        CurrentBusiness $currentBusiness,
        EnsurePrimaryMenuAction $ensurePrimaryMenu,
    ): RedirectResponse {
        $business = $this->currentBusiness($currentBusiness);
        Gate::authorize('create', Product::class);
        $menu = $ensurePrimaryMenu->execute($business);
        $data = $request->validated();
        $category = $this->resolveCategory($menu, $data['category_public_id']);

        DB::transaction(function () use ($business, $menu, $category, $data): void {
            $lockedMenu = Menu::query()->whereKey($menu->getKey())->lockForUpdate()->firstOrFail();
            $lockedCategory = $lockedMenu->categories()->whereKey($category->getKey())->firstOrFail();

            $this->enforcementFor($business)->requireLimit(
                $business,
                'max_products',
                $lockedMenu->products()->count(),
            );
            $this->enforceOptionalFeatures($business, $data);

            $sortOrder = (int) ($lockedCategory->products()->max('sort_order') ?? -1) + 1;

            $lockedCategory->products()->create([
                'menu_id' => $lockedMenu->getKey(),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'base_price' => $data['base_price'],
                'discount_price' => $data['discount_price'] ?? null,
                'is_available' => $data['is_available'] ?? true,
                'status' => $data['status'],
                'sort_order' => $sortOrder,
            ]);
        });

        return back()->with('success', 'محصول با موفقیت ایجاد شد.');
    }

    public function update(
        SaveProductRequest $request,
        string $productPublicId,
        CurrentBusiness $currentBusiness,
        EnsurePrimaryMenuAction $ensurePrimaryMenu,
    ): RedirectResponse {
        $business = $this->currentBusiness($currentBusiness);
        $menu = $this->currentMenu($currentBusiness, $ensurePrimaryMenu);
        $product = $this->resolveProduct($menu, $productPublicId);
        Gate::authorize('update', $product);

        $data = $request->validated();
        $category = $this->resolveCategory($menu, $data['category_public_id']);

        DB::transaction(function () use ($business, $menu, $product, $category, $data): void {
            $lockedMenu = Menu::query()->whereKey($menu->getKey())->lockForUpdate()->firstOrFail();
            $lockedProduct = $lockedMenu->products()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            $lockedCategory = $lockedMenu->categories()->whereKey($category->getKey())->firstOrFail();

            $this->enforceOptionalFeatures($business, $data);

            $lockedProduct->update([
                'category_id' => $lockedCategory->getKey(),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'base_price' => $data['base_price'],
                'discount_price' => $data['discount_price'] ?? null,
                'is_available' => $data['is_available'] ?? true,
                'status' => $data['status'],
            ]);
        });

        return back()->with('success', 'محصول با موفقیت ویرایش شد.');
    }

    public function destroy(
        string $productPublicId,
        CurrentBusiness $currentBusiness,
        EnsurePrimaryMenuAction $ensurePrimaryMenu,
    ): RedirectResponse {
        $menu = $this->currentMenu($currentBusiness, $ensurePrimaryMenu);
        $product = $this->resolveProduct($menu, $productPublicId);
        Gate::authorize('delete', $product);

        DB::transaction(function () use ($menu, $product): void {
            $lockedMenu = Menu::query()->whereKey($menu->getKey())->lockForUpdate()->firstOrFail();
            $lockedMenu->products()->whereKey($product->getKey())->firstOrFail()->delete();
        });

        return back()->with('success', 'محصول حذف شد.');
    }

    public function reorder(
        ReorderProductsRequest $request,
        string $categoryPublicId,
        CurrentBusiness $currentBusiness,
        EnsurePrimaryMenuAction $ensurePrimaryMenu,
    ): RedirectResponse {
        $menu = $this->currentMenu($currentBusiness, $ensurePrimaryMenu);
        $category = $this->resolveCategory($menu, $categoryPublicId);
        Gate::authorize('viewAny', Product::class);
        $publicIds = $request->validated('product_public_ids');

        DB::transaction(function () use ($menu, $category, $publicIds): void {
            $lockedMenu = Menu::query()->whereKey($menu->getKey())->lockForUpdate()->firstOrFail();
            $lockedCategory = $lockedMenu->categories()->whereKey($category->getKey())->firstOrFail();
            $products = $lockedCategory->products()->whereIn('public_id', $publicIds)->get();

            if ($products->count() !== count($publicIds)) {
                throw ValidationException::withMessages([
                    'product_public_ids' => 'همه محصولات باید متعلق به همین دسته‌بندی و منو باشند.',
                ]);
            }

            $sortOrders = $products->pluck('sort_order')->sort()->values();

            foreach ($publicIds as $index => $publicId) {
                $products->firstWhere('public_id', $publicId)?->update([
                    'sort_order' => $sortOrders[$index],
                ]);
            }
        });

        return back()->with('success', 'ترتیب محصولات ذخیره شد.');
    }

    private function currentBusiness(CurrentBusiness $currentBusiness): Business
    {
        $business = $currentBusiness->get();
        abort_unless($business instanceof Business, 404);

        return $business;
    }

    private function currentMenu(
        CurrentBusiness $currentBusiness,
        EnsurePrimaryMenuAction $ensurePrimaryMenu,
    ): Menu {
        return $ensurePrimaryMenu->execute($this->currentBusiness($currentBusiness));
    }

    private function resolveCategory(Menu $menu, string $publicId): Category
    {
        return $menu->categories()->where('public_id', $publicId)->first()
            ?? throw ValidationException::withMessages([
                'category_public_id' => 'دسته‌بندی انتخاب‌شده متعلق به این منو نیست.',
            ]);
    }

    private function resolveProduct(Menu $menu, string $publicId): Product
    {
        return $menu->products()->where('public_id', $publicId)->firstOrFail();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function enforceOptionalFeatures(Business $business, array $data): void
    {
        $enforcement = $this->enforcementFor($business);

        if (($data['discount_price'] ?? null) !== null) {
            $enforcement->requireFeature($business, 'product_discount');
        }

        if (($data['is_available'] ?? true) === false) {
            $enforcement->requireFeature($business, 'product_availability_toggle');
        }
    }

    private function enforcementFor(Business $business): EnforcementService
    {
        return new EnforcementService(new EntitlementService($business));
    }
}
