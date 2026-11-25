<?php

namespace App\Modules\PublicMenu\Http\Controllers;

use App\Enums\BusinessStatus;
use App\Modules\Business\Models\Business;
use App\Modules\Category\Models\Category;
use App\Modules\Menu\Enums\MenuStatus;
use App\Modules\Media\Models\ProductImage;
use App\Modules\Product\Enums\ProductStatus;
use App\Modules\Product\Models\Product;
use App\Modules\PublicMenu\Services\PublicMenuCache;
use Inertia\Inertia;
use Inertia\Response;

class PublicMenuController
{
    public function __construct(
        private readonly PublicMenuCache $publicMenuCache,
    ) {}

    public function show(string $businessSlug, string $menuSlug): Response
    {
        $business = Business::query()
            ->where('slug', $businessSlug)
            ->where('status', BusinessStatus::Active)
            ->firstOrFail();

        $menu = $business->menu()
            ->where('slug', $menuSlug)
            ->where('status', MenuStatus::Published)
            ->firstOrFail();

        $publicMenu = $this->publicMenuCache->remember($menu, function () use ($menu, $business): array {
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
                ],
                'categories' => $categories->map(fn (Category $category): array => [
                    'name' => $category->name,
                    'products' => $category->products->map(fn (Product $product): array => [
                        'name' => $product->name,
                        'description' => $product->description,
                        'price' => $product->base_price,
                        'discount_price' => $product->discount_price,
                        'images' => $product->images->map(fn (ProductImage $image): array => [
                            'url' => route('media.public', $image->media->public_id),
                            'width' => $image->media->width,
                            'height' => $image->media->height,
                        ])->values()->all(),
                        'images' => $product->images->map(fn ($image): array => [
                            'url' => route('media.public', $image->media->public_id),
                            'width' => $image->media->width,
                            'height' => $image->media->height,
                        ])->values()->all(),
                    ])->values()->all(),
                ])->values()->all(),
            ];
        });

        return Inertia::render('PublicMenu/Show', $publicMenu);
    }
}
