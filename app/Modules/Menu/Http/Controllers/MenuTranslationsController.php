<?php

namespace App\Modules\Menu\Http\Controllers;

use App\Modules\Business\Models\Business;
use App\Modules\Localization\Services\LocaleRegistry;
use App\Modules\Menu\Actions\EnsurePrimaryMenuAction;
use App\Modules\Menu\Actions\SaveMenuTranslationsAction;
use App\Modules\Menu\Http\Requests\SaveMenuTranslationsRequest;
use App\Modules\Menu\Models\Menu;
use App\Modules\PublicMenu\Services\PublicMenuCache;
use App\Modules\Tenancy\Services\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MenuTranslationsController
{
    public function edit(
        string $locale,
        CurrentBusiness $currentBusiness,
        EnsurePrimaryMenuAction $ensurePrimaryMenu,
        LocaleRegistry $localeRegistry,
    ): Response {
        $business = $this->currentBusiness($currentBusiness);
        Gate::authorize('update', $business);

        $menu = $ensurePrimaryMenu->execute($business);
        $this->authorizeLocale($business, $menu, $locale, $localeRegistry);

        $menuTranslation = $menu->translations()->where('locale', $locale)->first();
        $categories = $menu->categories()
            ->with(['translations' => fn ($query) => $query->where('locale', $locale)])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $products = $menu->products()
            ->with(['translations' => fn ($query) => $query->where('locale', $locale)])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $locales = collect($localeRegistry->availableFor($business))
            ->reject(fn (array $metadata, string $code): bool => $code === $menu->default_locale)
            ->map(fn (array $metadata, string $code): array => [
                'code' => $code,
                ...$metadata,
            ])
            ->values();

        return Inertia::render('Menu/Translations', [
            'locale' => [
                'code' => $locale,
                ...$localeRegistry->supported()[$locale],
            ],
            'locales' => $locales,
            'menu' => [
                'source_title' => $menu->title,
                'translation_title' => $menuTranslation->title ?? '',
            ],
            'categories' => $categories->map(fn ($category): array => [
                'public_id' => $category->public_id,
                'source_name' => $category->name,
                'translation_name' => $category->translations->first()->name ?? '',
            ])->values(),
            'products' => $products->map(fn ($product): array => [
                'public_id' => $product->public_id,
                'source_name' => $product->name,
                'source_description' => $product->description,
                'translation_name' => $product->translations->first()->name ?? '',
                'translation_description' => $product->translations->first()->description ?? '',
            ])->values(),
        ]);
    }

    public function update(
        SaveMenuTranslationsRequest $request,
        string $locale,
        CurrentBusiness $currentBusiness,
        EnsurePrimaryMenuAction $ensurePrimaryMenu,
        LocaleRegistry $localeRegistry,
        SaveMenuTranslationsAction $saveTranslations,
        PublicMenuCache $publicMenuCache,
    ): RedirectResponse {
        $business = $this->currentBusiness($currentBusiness);
        Gate::authorize('update', $business);

        $menu = $ensurePrimaryMenu->execute($business);
        $this->authorizeLocale($business, $menu, $locale, $localeRegistry);
        /** @var array{menu_title: string, categories: list<array{public_id: string, name: string}>, products: list<array{public_id: string, name: string, description?: string|null}>} $data */
        $data = $request->validated();

        $saveTranslations->execute($menu, $locale, $data);

        $publicMenuCache->forget($menu);

        return redirect()
            ->route('catalog.menu.translations.edit', $locale)
            ->with('success', 'ترجمه منو ذخیره شد.');
    }

    private function currentBusiness(CurrentBusiness $currentBusiness): Business
    {
        $business = $currentBusiness->get();
        abort_unless($business instanceof Business, 404);

        return $business;
    }

    private function authorizeLocale(
        Business $business,
        Menu $menu,
        string $locale,
        LocaleRegistry $localeRegistry,
    ): void {
        abort_unless(
            $locale !== $menu->default_locale && $localeRegistry->allows($business, $locale),
            403,
        );
    }
}
