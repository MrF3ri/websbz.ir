<?php

namespace App\Modules\PublicMenu\Http\Controllers;

use App\Enums\BusinessStatus;
use App\Modules\Business\Models\Business;
use App\Modules\Category\Models\Category;
use App\Modules\Media\Models\ProductImage;
use App\Modules\Menu\Enums\MenuStatus;
use App\Modules\Menu\Models\Menu;
use App\Modules\Product\Enums\ProductStatus;
use App\Modules\Product\Models\Product;
use App\Modules\PublicMenu\Services\PublicMenuCache;
use App\Modules\Subscription\Services\EntitlementService;
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
     * @return array{template: string, primary_color: string, font: string, mode: string}
     */
    private function themeData(Menu $menu): array
    {
        $theme = $menu->theme ?? [];
        $template = $theme['template'] ?? 'classic';
        $color = $theme['primary_color'] ?? '#365B4A';
        $font = $theme['font'] ?? 'system';
        $mode = $theme['mode'] ?? 'light';

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
        ];
    }

    /**
     * @return array{public_id: string, name: string, description: string|null, label: string|null, price: int, discount_price: int|null, images: array<int, array{url: string, srcset: string, sizes: string, width: int, height: int}>}
     */
    private function productData(Product $product): array
    {
        return [
            'public_id' => $product->public_id,
            'name' => $product->name,
            'description' => $product->description,
            'label' => $product->label?->value,
            'price' => $product->base_price,
            'discount_price' => $product->discount_price,
            'images' => $product->images->map(fn (ProductImage $image): array => $this->imageData($image))->values()->all(),
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

        if (! is_array($banner)) {
            return null;
        }

        $title = is_string($banner['title'] ?? null) ? mb_substr(trim($banner['title']), 0, 100) : null;
        $message = is_string($banner['message'] ?? null) ? mb_substr(trim($banner['message']), 0, 300) : null;
        $ctaLabel = is_string($banner['cta_label'] ?? null) ? mb_substr(trim($banner['cta_label']), 0, 40) : null;
        $ctaUrl = $banner['cta_url'] ?? null;

        if (
            ! is_string($ctaUrl)
            || ! str_starts_with($ctaUrl, 'https://')
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

    public function show(
        string $businessSlug,
        string $menuSlug,
        PublicMenuCache $publicMenuCache,
    ): Response {
        $business = Business::query()
            ->where('slug', $businessSlug)
            ->where('status', BusinessStatus::Active)
            ->firstOrFail();

        $menu = $business->menu()
            ->where('slug', $menuSlug)
            ->where('status', MenuStatus::Published)
            ->firstOrFail();

        $publicMenu = $publicMenuCache->remember($menu, function () use ($menu, $business): array {
            $logo = $business->logo()->with('media')->first();
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
                }, 'products.images.media'])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            return [
                'business' => [
                    'name' => $business->name,
                    'slug' => $business->slug,
                    'logo_url' => $logo ? route('media.business-logo', [$business->public_id, $logo->media->public_id]) : null,
                ],
                'menu' => [
                    'title' => $menu->title,
                    'slug' => $menu->slug,
                    'locale' => $menu->default_locale,
                    'theme' => $this->themeData($menu),
                ],
                'categories' => $categories->map(fn (Category $category): array => [
                    'name' => $category->name,
                    'products' => $category->products->map(fn (Product $product): array => $this->productData($product))->values()->all(),
                ])->values()->all(),
            ];
        });

        if (is_array($publicMenu) && is_array($publicMenu['menu'] ?? null) && is_array($publicMenu['business'] ?? null)) {
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
