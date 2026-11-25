<?php

namespace App\Modules\PublicMenu\Http\Controllers;

use App\Enums\BusinessStatus;
use App\Modules\Business\Models\Business;
use App\Modules\Category\Models\Category;
use App\Modules\Media\Models\ProductImage;
use App\Modules\Menu\Enums\MenuStatus;
use App\Modules\Product\Enums\ProductStatus;
use App\Modules\Product\Models\Product;
use App\Modules\PublicMenu\Services\PublicMenuCache;
use Inertia\Inertia;
use Inertia\Response;

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
     * @return array{name: string, description: string|null, price: int, discount_price: int|null, images: array<int, array{url: string, srcset: string, sizes: string, width: int, height: int}>}
     */
    private function productData(Product $product): array
    {
        return [
            'name' => $product->name,
            'description' => $product->description,
            'price' => $product->base_price,
            'discount_price' => $product->discount_price,
            'images' => $product->images->map(fn (ProductImage $image): array => $this->imageData($image))->values()->all(),
        ];
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
                ],
                'menu' => [
                    'title' => $menu->title,
                    'slug' => $menu->slug,
                    'locale' => $menu->default_locale,
                    'theme' => array_merge([
                        'template' => 'classic',
                        'primary_color' => '#365B4A',
                        'font' => 'system',
                        'mode' => 'light',
                    ], array_intersect_key($menu->theme ?? [], array_flip(['template', 'primary_color', 'font', 'mode']))),
                ],
                'categories' => $categories->map(fn (Category $category): array => [
                    'name' => $category->name,
                    'products' => $category->products->map(fn (Product $product): array => $this->productData($product))->values()->all(),
                ])->values()->all(),
            ];
        });

        return Inertia::render('PublicMenu/Show', $publicMenu);
    }
}
