<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Business\Models\Business;
use App\Modules\Category\Models\Category;
use App\Modules\Menu\Actions\EnsurePrimaryMenuAction;
use App\Modules\Menu\Models\Menu;
use App\Modules\Product\Enums\ProductStatus;
use App\Modules\Product\Models\Product;
use App\Modules\Tenancy\Services\CurrentBusiness;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController
{
    public function index(
        Request $request,
        CurrentBusiness $currentBusiness,
        EnsurePrimaryMenuAction $ensurePrimaryMenu,
    ): Response {
        Gate::authorize('viewAny', Category::class);
        Gate::authorize('viewAny', Product::class);

        $business = $currentBusiness->get();
        abort_unless($business instanceof Business, 404);

        $menu = $ensurePrimaryMenu->execute($business);

        $search = trim((string) $request->query('search', ''));
        $categoryPublicId = $request->query('category');
        $status = $request->query('status');

        $categories = $menu->categories()
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'public_id', 'name', 'sort_order', 'is_active']);

        $productsQuery = $menu->products()
            ->with('category:id,public_id,name')
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($search !== '') {
            $productsQuery->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($search).'%']);
        }

        if (is_string($categoryPublicId) && $categoryPublicId !== '') {
            $category = $menu->categories()
                ->where('public_id', $categoryPublicId)
                ->firstOrFail();

            $productsQuery->where('category_id', $category->getKey());
        }

        if (is_string($status) && $status !== '') {
            abort_unless(in_array($status, [
                ProductStatus::Draft->value,
                ProductStatus::Published->value,
            ], true), 404);

            $productsQuery->where('status', $status);
        }

        $products = $productsQuery->paginate(20)->withQueryString();

        return Inertia::render('Catalog/Index', [
            'menu' => [
                'public_id' => $menu->public_id,
                'title' => $menu->title,
                'status' => $menu->status->value,
                'default_locale' => $menu->default_locale,
            ],
            'categories' => $categories->map(fn (Category $category): array => [
                'public_id' => $category->public_id,
                'name' => $category->name,
                'sort_order' => $category->sort_order,
                'is_active' => $category->is_active,
                'products_count' => $category->products_count,
            ])->values(),
            'products' => $products->through(fn (Product $product): array => [
                'public_id' => $product->public_id,
                'category_public_id' => $product->category->public_id,
                'category_name' => $product->category->name,
                'name' => $product->name,
                'description' => $product->description,
                'base_price' => $product->base_price,
                'discount_price' => $product->discount_price,
                'is_available' => $product->is_available,
                'status' => $product->status->value,
                'sort_order' => $product->sort_order,
            ]),
            'usage' => [
                'categories' => $menu->categories()->count(),
                'products' => $menu->products()->count(),
            ],
            'filters' => [
                'search' => $search,
                'category' => is_string($categoryPublicId) ? $categoryPublicId : '',
                'status' => is_string($status) ? $status : '',
            ],
        ]);
    }
}
