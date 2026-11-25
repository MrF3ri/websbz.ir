<?php

namespace App\Modules\PublicMenu\Http\Controllers;

use App\Enums\BusinessStatus;
use App\Modules\Branch\Models\Branch;
use App\Modules\Branch\Models\BranchProductSetting;
use App\Modules\Branch\Services\BranchProductResolver;
use App\Modules\Business\Models\Business;
use App\Modules\Category\Models\Category;
use App\Modules\Localization\Services\LocaleRegistry;
use App\Modules\Media\Models\ProductImage;
use App\Modules\Menu\Enums\MenuStatus;
use App\Modules\Menu\Models\Menu;
use App\Modules\Product\Enums\ProductStatus;
use App\Modules\Product\Models\Product;
use App\Modules\PublicMenu\Services\PublicMenuCache;
use App\Modules\Subscription\Services\EntitlementService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

class PublicMenuController
{
    /**
     * @return array{url: string, srcset: string, sizes: string, width: int, height: int}
     */
    private function imageData(ProductImage $image): array
    {
        $media = $image->media;
        $srcset = [];

        foreach (['thumbnail', 'medium'] as $variantName) {
            $variant = $media->variants[$variantName] ?? null;

            if (is_array($variant) && $variant['width'] < $media->width) {
                $srcset[] = route('media.public', [
                    'mediaPublicId' => $media->public_id,
                    'variant' => $variantName,
                ]).' '.$variant['width'].'w';
            }
        }

        $originalUrl = route('media.public', $media->public_id);
        $srcset[] = $originalUrl.' '.$media->width.'w';

        return [
            'url' => $originalUrl,
            'srcset' => implode(', ', $srcset),
            'sizes' => '(max-width: 640px) 44vw, 30vw',
            'width' => $media->width,
            'height' => $media->height,
        ];
    }

    /**
     * Resolve only supported presentation tokens; persisted JSON is never trusted
     * as a source of arbitrary CSS values, classes, or markup.
     *
     * @return array{template: string, primary_color: string, font: string, mode: string, density: string, card_style: string, heading_size: string}
     */
    private function themeData(Menu $menu): array
    {
        $theme = $menu->theme ?? [];
        $template = $theme['template'] ?? 'classic';
        $color = $theme['primary_color'] ?? '#365B4A';
        $font = $theme['font'] ?? 'system';
        $mode = $theme['mode'] ?? 'light';
        $density = $theme['density'] ?? 'comfortable';
        $cardStyle = $theme['card_style'] ?? 'standard';
        $headingSize = $theme['heading_size'] ?? 'normal';

        return [
            'template' => in_array($template, ['classic', 'minimal', 'editorial', 'modern', 'cafe', 'contrast'], true)
                ? $template
                : 'classic',
            'primary_color' => preg_match('/^#[0-9A-Fa-f]{6}$/', $color) === 1
                ? strtoupper($color)
                : '#365B4A',
            'font' => in_array($font, ['system', 'serif', 'rounded'], true)
                ? $font
                : 'system',
            'mode' => in_array($mode, ['light', 'dark'], true)
                ? $mode
                : 'light',
            'density' => in_array($density, ['compact', 'comfortable', 'spacious'], true)
                ? $density
                : 'comfortable',
            'card_style' => in_array($cardStyle, ['standard', 'flat', 'elevated'], true)
                ? $cardStyle
                : 'standard',
            'heading_size' => in_array($headingSize, ['normal', 'large', 'display'], true)
                ? $headingSize
                : 'normal',
        ];
    }

    /**
     * @param  array<string, mixed>  $effective
     * @return array{public_id: string, name: string, description: string|null, branch_name_override: bool, branch_description_override: bool, label: string|null, price: int, discount_price: int|null, images: array<int, array{url: string, srcset: string, sizes: string, width: int, height: int}>, translations: array<string, array{name: string, description: string|null}>}
     */
    private function productData(Product $product, array $effective, ?BranchProductSetting $setting): array
    {
        return [
            'public_id' => $product->public_id,
            'name' => $effective['name'],
            'description' => $effective['description'],
            'branch_name_override' => $setting !== null && $setting->name_override !== null,
            'branch_description_override' => $setting !== null && $setting->description_override_enabled,
            'label' => $product->label?->value,
            'price' => $effective['base_price'],
            'discount_price' => $effective['discount_price'],
            'images' => $product->images->map(fn (ProductImage $image): array => $this->imageData($image))->values()->all(),
            'translations' => $product->translations->keyBy('locale')->map(fn ($translation): array => [
                'name' => $translation->name,
                'description' => $translation->description,
            ])->all(),
        ];
    }

    /**
     * Return only validated banner fields. Persisted JSON is not trusted as markup or a URL source.
     *
     * @return array{title: string|null, message: string|null, cta_label: string|null, cta_url: string|null}|null
     */
    private function bannerData(Menu $menu): ?array
    {
        $banner = $menu->advertising_banner;

        if (is_array($banner) === false) {
            return null;
        }

        $title = is_string($banner['title'] ?? null) ? mb_substr(trim($banner['title']), 0, 100) : null;
        $message = is_string($banner['message'] ?? null) ? mb_substr(trim($banner['message']), 0, 300) : null;
        $ctaLabel = is_string($banner['cta_label'] ?? null) ? mb_substr(trim($banner['cta_label']), 0, 40) : null;
        $ctaUrl = $banner['cta_url'] ?? null;

        if (
            is_string($ctaUrl) === false
            || str_starts_with($ctaUrl, 'https://') === false
            || filter_var($ctaUrl, FILTER_VALIDATE_URL) === false
            || $ctaLabel === null
            || $ctaLabel === ''
        ) {
            $ctaLabel = null;
            $ctaUrl = null;
        }

        $data = [
            'title' => $title !== '' ? $title : null,
            'message' => $message !== '' ? $message : null,
            'cta_label' => $ctaLabel,
            'cta_url' => $ctaUrl,
        ];

        return count(array_filter($data, fn ($value): bool => $value !== null)) > 0
            ? $data
            : null;
    }

    public function showPrimary(
        string $businessSlug,
        string $menuSlug,
        BranchProductResolver $branchProductResolver,
        PublicMenuCache $publicMenuCache,
        Request $request,
        LocaleRegistry $localeRegistry,
    ): Response {
        return $this->renderMenu($businessSlug, $menuSlug, null, $branchProductResolver, $publicMenuCache, $request, $localeRegistry);
    }

    public function showBranch(
        string $businessSlug,
        string $menuSlug,
        string $branchSlug,
        BranchProductResolver $branchProductResolver,
        PublicMenuCache $publicMenuCache,
        Request $request,
        LocaleRegistry $localeRegistry,
    ): Response {
        return $this->renderMenu($businessSlug, $menuSlug, $branchSlug, $branchProductResolver, $publicMenuCache, $request, $localeRegistry);
    }

    private function renderMenu(
        string $businessSlug,
        string $menuSlug,
        ?string $branchSlug,
        BranchProductResolver $resolver,
        PublicMenuCache $publicMenuCache,
        Request $request,
        LocaleRegistry $localeRegistry,
    ): Response {
        $business = Business::query()
            ->where('slug', $businessSlug)
            ->where('status', BusinessStatus::Active)
            ->firstOrFail();

        $menu = $business->menu()
            ->where('slug', $menuSlug)
            ->where('status', MenuStatus::Published)
            ->firstOrFail();

        $branchQuery = Branch::query()
            ->where('business_id', $business->getKey())
            ->where('status', 'active');

        $branch = $branchSlug !== null
            ? $branchQuery->where('slug', $branchSlug)->firstOrFail()
            : $branchQuery->where('is_primary', true)->firstOrFail();

        $publicMenu = $publicMenuCache->remember($menu, function () use ($menu, $business, $branch, $resolver): array {
            $logo = $business->logo()->with('media')->first();
            $categorySettings = $branch->categorySettings()->get()->keyBy('category_id');
            $productSettings = $branch->productSettings()->get()->keyBy('product_id');
            $categories = $menu->categories()
                ->where('is_active', true)
                ->whereHas('products', function ($query) use ($menu): void {
                    $query
                        ->where('products.menu_id', $menu->getKey())
                        ->where('status', ProductStatus::Published)
                        ->where('is_available', true);
                })
                ->with(['products' => function ($query) use ($menu): void {
                    $query
                        ->where('products.menu_id', $menu->getKey())
                        ->where('status', ProductStatus::Published)
                        ->where('is_available', true)
                        ->orderBy('sort_order')
                        ->orderBy('id');
                }, 'translations', 'products.images.media', 'products.translations'])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(function (Category $category) use ($categorySettings, $productSettings, $resolver): ?array {
                    $categorySetting = $categorySettings->get($category->getKey());

                    if ($categorySetting !== null && $categorySetting->is_visible === false) {
                        return null;
                    }

                    $products = $category->products
                        ->map(function (Product $product) use ($productSettings, $resolver): ?array {
                            $setting = $productSettings->get($product->getKey());
                            $effective = $resolver->resolve($product, $setting);

                            if ($effective['is_visible'] === false || $effective['is_available'] === false) {
                                return null;
                            }

                            return array_merge(
                                $this->productData($product, $effective, $setting),
                                ['_sort_order' => $effective['sort_order']],
                            );
                        })
                        ->filter()
                        ->sortBy('_sort_order')
                        ->values()
                        ->map(function (array $product): array {
                            unset($product['_sort_order']);

                            return $product;
                        })
                        ->all();

                    if ($products === []) {
                        return null;
                    }

                    return [
                        'name' => $category->name,
                        'sort_order' => $categorySetting !== null ? $categorySetting->sort_order : $category->sort_order,
                        'translations' => $category->translations->keyBy('locale')->map(fn ($translation): array => [
                            'name' => $translation->name,
                        ])->all(),
                        'products' => $products,
                    ];
                })
                ->filter()
                ->sortBy('sort_order')
                ->values()
                ->map(function (array $category): array {
                    unset($category['sort_order']);

                    return $category;
                })
                ->all();

            return [
                'business' => [
                    'name' => $business->name,
                    'slug' => $business->slug,
                    'logo_url' => $logo ? route('media.business-logo', [$business->public_id, $logo->media->public_id]) : null,
                ],
                'branch' => [
                    'name' => $branch->name,
                    'slug' => $branch->slug,
                    'phone' => $branch->phone,
                    'address' => $branch->address,
                    'latitude' => $branch->latitude,
                    'longitude' => $branch->longitude,
                    'timezone' => $branch->timezone,
                    'opening_hours' => $branch->opening_hours,
                ],
                'menu' => [
                    'title' => $menu->title,
                    'slug' => $menu->slug,
                    'locale' => $menu->default_locale,
                    'translations' => $menu->translations->keyBy('locale')->map(fn ($translation): array => [
                        'title' => $translation->title,
                    ])->all(),
                    'theme' => $this->themeData($menu),
                ],
                'categories' => $categories,
            ];
        }, $branch);

        if (is_array($publicMenu) && is_array($publicMenu['menu'] ?? null) && is_array($publicMenu['business'] ?? null)) {
            $availableLocales = $localeRegistry->availableFor($business);
            $requestedLocale = $request->query('lang');
            $locale = is_string($requestedLocale) && $localeRegistry->allows($business, $requestedLocale)
                ? $requestedLocale
                : $menu->default_locale;

            $menuTranslation = $publicMenu['menu']['translations'][$locale] ?? null;
            $publicMenu['menu']['title'] = $menuTranslation['title'] ?? $publicMenu['menu']['title'];
            unset($publicMenu['menu']['translations']);
            $publicMenu['menu']['source_locale'] = $menu->default_locale;
            $publicMenu['menu']['locale'] = $locale;
            $publicMenu['menu']['direction'] = $localeRegistry->direction($locale);
            $publicMenu['menu']['public_url'] = route('public.menu.branch.show', [$business->slug, $menu->slug, $branch->slug]);
            $publicMenu['menu']['available_locales'] = collect($availableLocales)
                ->map(fn (array $details, string $code): array => ['code' => $code, ...$details])
                ->values()
                ->all();

            $publicMenu['categories'] = array_map(function (array $category) use ($locale): array {
                $categoryTranslation = $category['translations'][$locale] ?? null;
                $category['name'] = $categoryTranslation['name'] ?? $category['name'];
                unset($category['translations']);

                $category['products'] = array_map(function (array $product) use ($locale): array {
                    $translation = $product['translations'][$locale] ?? null;
                    if (is_array($translation)) {
                        if ($product['branch_name_override'] === false) {
                            $product['name'] = $translation['name'] ?? $product['name'];
                        }
                        if ($product['branch_description_override'] === false) {
                            $product['description'] = $translation['description'] ?? $product['description'];
                        }
                    }
                    unset($product['translations'], $product['branch_name_override'], $product['branch_description_override']);

                    return $product;
                }, $category['products']);

                return $category;
            }, $publicMenu['categories']);

            $entitlements = new EntitlementService($business);
            $publicMenu['menu']['image_slider_enabled'] = $entitlements->hasFeature('image_slider');
            $publicMenu['menu']['advertising_banner'] = $entitlements->hasFeature('advertising_banner')
                ? $this->bannerData($menu)
                : null;
            $publicMenu['business']['introduction'] = $entitlements->hasFeature('business_introduction')
                ? $business->introduction
                : null;
        } else {
            throw new LogicException('The cached public menu payload is invalid.');
        }

        return Inertia::render('PublicMenu/Show', $publicMenu);
    }
}
