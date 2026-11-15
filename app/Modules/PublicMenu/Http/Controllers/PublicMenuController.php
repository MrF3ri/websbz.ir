<?php

namespace App\\Modules\\PublicMenu\\Http\\Controllers;

use App\\Enums\\BusinessStatus;
use App\\Modules\\Business\\Models\\Business;
use App\\Modules\\Menu\\Enums\\MenuStatus;
use App\\Modules\\Product\\Enums\\ProductStatus;
use Illuminate\\View\\View;
use Inertia\\Inertia;
use Inertia\\Response;

class PublicMenuController
{
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

        $categories = $menu->categories()
            ->where('is_active', true)
            ->whereHas('products', function ($query): void {
                $query
                    ->where('status', ProductStatus::Published)
                    ->where('is_available', true);
            })
            ->with(['products' => function ($query): void {
                $query
                    ->where('status', ProductStatus::Published)
                    ->where('is_available', true)
                    ->orderBy('sort_order')
                    ->orderBy('id');
            }])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return Inertia::render('PublicMenu/Show', [
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
                ])->values(),
            ])->values(),
        ]);
    }
}
