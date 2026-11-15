<?php

namespace App\Modules\Category\Http\Controllers;

use App\Modules\Business\Models\Business;
use App\Modules\Category\Actions\CreateCategoryAction;
use App\Modules\Category\Http\Requests\ReorderCategoriesRequest;
use App\Modules\Category\Http\Requests\SaveCategoryRequest;
use App\Modules\Category\Models\Category;
use App\Modules\Menu\Actions\EnsurePrimaryMenuAction;
use App\Modules\Menu\Models\Menu;
use App\Modules\Tenancy\Services\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CategoryController
{
    public function store(
        SaveCategoryRequest $request,
        CurrentBusiness $currentBusiness,
        EnsurePrimaryMenuAction $ensurePrimaryMenu,
        CreateCategoryAction $createCategory,
    ): RedirectResponse {
        $business = $this->currentBusiness($currentBusiness);
        Gate::authorize('create', Category::class);
        $menu = $ensurePrimaryMenu->execute($business);
        $data = $request->validated();

        $createCategory->execute(
            $business,
            $menu,
            $data['name'],
            $data['is_active'] ?? true,
        );

        return back()->with('success', 'دسته‌بندی با موفقیت ایجاد شد.');
    }

    public function update(
        SaveCategoryRequest $request,
        string $categoryPublicId,
        CurrentBusiness $currentBusiness,
        EnsurePrimaryMenuAction $ensurePrimaryMenu,
    ): RedirectResponse {
        $menu = $this->currentMenu($currentBusiness, $ensurePrimaryMenu);
        $category = $this->resolveCategory($menu, $categoryPublicId);
        Gate::authorize('update', $category);

        $category->update($request->validated());

        return back()->with('success', 'دسته‌بندی با موفقیت ویرایش شد.');
    }

    public function destroy(
        string $categoryPublicId,
        CurrentBusiness $currentBusiness,
        EnsurePrimaryMenuAction $ensurePrimaryMenu,
    ): RedirectResponse {
        $menu = $this->currentMenu($currentBusiness, $ensurePrimaryMenu);
        $category = $this->resolveCategory($menu, $categoryPublicId);
        Gate::authorize('delete', $category);

        DB::transaction(function () use ($menu, $category): void {
            $lockedMenu = Menu::query()->whereKey($menu->getKey())->lockForUpdate()->firstOrFail();
            $lockedCategory = $lockedMenu->categories()
                ->whereKey($category->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedCategory->products()->exists()) {
                throw ValidationException::withMessages([
                    'category' => 'ابتدا محصولات این دسته‌بندی را منتقل یا حذف کنید.',
                ]);
            }

            $lockedCategory->delete();
        });

        return back()->with('success', 'دسته‌بندی حذف شد.');
    }

    public function reorder(
        ReorderCategoriesRequest $request,
        CurrentBusiness $currentBusiness,
        EnsurePrimaryMenuAction $ensurePrimaryMenu,
    ): RedirectResponse {
        $menu = $this->currentMenu($currentBusiness, $ensurePrimaryMenu);
        Gate::authorize('viewAny', Category::class);
        $publicIds = $request->validated('category_public_ids');

        DB::transaction(function () use ($menu, $publicIds): void {
            $lockedMenu = Menu::query()->whereKey($menu->getKey())->lockForUpdate()->firstOrFail();
            $categories = $lockedMenu->categories()
                ->whereIn('public_id', $publicIds)
                ->get();

            if ($categories->count() !== count($publicIds)) {
                throw ValidationException::withMessages([
                    'category_public_ids' => 'همه دسته‌بندی‌ها باید متعلق به همین منو باشند.',
                ]);
            }

            $sortOrders = $categories->pluck('sort_order')->sort()->values();

            foreach ($publicIds as $index => $publicId) {
                $categories->firstWhere('public_id', $publicId)?->update([
                    'sort_order' => $sortOrders[$index],
                ]);
            }
        });

        return back()->with('success', 'ترتیب دسته‌بندی‌ها ذخیره شد.');
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
        return $menu->categories()->where('public_id', $publicId)->firstOrFail();
    }
}
