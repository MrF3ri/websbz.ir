<?php

namespace App\Modules\Branch\Http\Controllers;

use App\Modules\Branch\Actions\UpdateBranchCatalogSettingsAction;
use App\Modules\Branch\Http\Requests\SaveBranchCatalogRequest;
use App\Modules\Branch\Models\Branch;
use App\Modules\Branch\Services\BranchProductResolver;
use App\Modules\Business\Models\Business;
use App\Modules\Category\Models\Category;
use App\Modules\Menu\Models\Menu;
use App\Modules\Product\Models\Product;
use App\Modules\PublicMenu\Services\PublicMenuCache;
use App\Modules\Tenancy\Services\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BranchCatalogController
{
    public function show(string $branchPublicId, CurrentBusiness $currentBusiness): Response
    {
        $business = $this->resolveBusiness($currentBusiness);
        Gate::authorize('view', $business);
        $branch = $this->resolveBranch($business, $branchPublicId);
        $menu = Menu::query()->where('business_id', $business->getKey())->firstOrFail();

        $categories = $menu->categories()
            ->with(['branchSettings' => fn ($query) => $query->where('branch_id', $branch->getKey())])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Category $category): array => [
                'public_id' => $category->public_id,
                'name' => $category->name,
                'is_active' => $category->is_active,
                'is_visible' => $category->branchSettings->first()?->is_visible ?? true,
                'sort_order' => $category->branchSettings->first()?->sort_order ?? $category->sort_order,
            ])->values();

        $resolver = app(BranchProductResolver::class);
        $products = $menu->products()
            ->with(['category', 'branchSettings' => fn ($query) => $query->where('branch_id', $branch->getKey())])
            ->orderBy('category_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (Product $product) use ($resolver): array {
                $setting = $product->branchSettings->first();

                return [
                    'public_id' => $product->public_id,
                    'category_name' => $product->category?->name,
                    'name' => $product->name,
                    'description' => $product->description,
                    'base_price' => $product->base_price,
                    'discount_price' => $product->discount_price,
                    'name_override' => $setting?->name_override,
                    'description_override_enabled' => $setting?->description_override_enabled ?? false,
                    'description_override' => $setting?->description_override,
                    'base_price_override' => $setting?->base_price_override,
                    'discount_override_mode' => $setting?->discount_override_mode ?? 'inherit',
                    'discount_price_override' => $setting?->discount_price_override,
                    'is_visible' => $setting?->is_visible ?? true,
                    'is_available' => $setting?->is_available ?? true,
                    'track_inventory' => $setting?->track_inventory ?? false,
                    'stock_quantity' => $setting?->stock_quantity,
                    'sort_order' => $setting?->sort_order ?? $product->sort_order,
                    'effective' => $resolver->resolve($product, $setting),
                ];
            })->values();

        return Inertia::render('Branches/Catalog', [
            'branch' => [
                'public_id' => $branch->public_id,
                'name' => $branch->name,
                'is_primary' => $branch->is_primary,
            ],
            'categories' => $categories,
            'products' => $products,
            'canManage' => Gate::allows('update', $business),
        ]);
    }

    public function update(
        SaveBranchCatalogRequest $request,
        string $branchPublicId,
        CurrentBusiness $currentBusiness,
        UpdateBranchCatalogSettingsAction $updateSettings,
        PublicMenuCache $publicMenuCache,
    ): RedirectResponse {
        $business = $this->resolveBusiness($currentBusiness);
        $branch = $this->resolveBranch($business, $branchPublicId);
        $updateSettings->execute($business, $branch, $request->validated());
        $menu = $business->menu()->first();

        if ($menu instanceof Menu) {
            $publicMenuCache->forget($menu, $branch);
        }

        return to_route('business.branches.catalog', $branch->public_id)
            ->with('success', 'تنظیمات منوی شعبه ذخیره شد.');
    }

    private function resolveBusiness(CurrentBusiness $currentBusiness): Business
    {
        $business = $currentBusiness->get();
        abort_unless($business instanceof Business, 404);

        return $business;
    }

    private function resolveBranch(Business $business, string $publicId): Branch
    {
        return $business->branches()->where('public_id', $publicId)->firstOrFail();
    }
}
