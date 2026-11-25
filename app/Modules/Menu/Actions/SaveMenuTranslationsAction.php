<?php

namespace App\Modules\Menu\Actions;

use App\Modules\Menu\Models\Menu;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveMenuTranslationsAction
{
    /**
     * @param  array{menu_title: string, categories: list<array{public_id: string, name: string}>, products: list<array{public_id: string, name: string, description?: string|null}>}  $data
     */
    public function execute(Menu $menu, string $locale, array $data): void
    {
        DB::transaction(function () use ($menu, $locale, $data): void {
            $lockedMenu = Menu::query()
                ->where('business_id', $menu->business_id)
                ->whereKey($menu->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $categories = $lockedMenu->categories()->lockForUpdate()->get();
            $products = $lockedMenu->products()->lockForUpdate()->get();

            $submittedCategoryIds = collect($data['categories'])
                ->pluck('public_id')
                ->sort()
                ->values()
                ->all();
            $expectedCategoryIds = $categories->pluck('public_id')->sort()->values()->all();

            $submittedProductIds = collect($data['products'])
                ->pluck('public_id')
                ->sort()
                ->values()
                ->all();
            $expectedProductIds = $products->pluck('public_id')->sort()->values()->all();

            $errors = [];

            if ($submittedCategoryIds !== $expectedCategoryIds) {
                $errors['categories'] = 'فهرست دسته‌بندی‌ها باید دقیقاً متعلق به همین منو باشد.';
            }

            if ($submittedProductIds !== $expectedProductIds) {
                $errors['products'] = 'فهرست محصولات باید دقیقاً متعلق به همین منو باشد.';
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $lockedMenu->translations()->updateOrCreate(
                ['locale' => $locale],
                ['title' => $data['menu_title']],
            );

            foreach ($data['categories'] as $translation) {
                $category = $categories->firstWhere('public_id', $translation['public_id']);

                $category->translations()->updateOrCreate(
                    ['locale' => $locale],
                    ['name' => $translation['name']],
                );
            }

            foreach ($data['products'] as $translation) {
                $product = $products->firstWhere('public_id', $translation['public_id']);

                $product->translations()->updateOrCreate(
                    ['locale' => $locale],
                    [
                        'name' => $translation['name'],
                        'description' => $translation['description'] ?? null,
                    ],
                );
            }
        });
    }
}
