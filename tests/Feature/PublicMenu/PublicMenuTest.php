<?php

namespace Tests\Feature\PublicMenu;

use App\Enums\BusinessStatus;
use App\Modules\Menu\Enums\MenuStatus;
use App\Modules\Product\Enums\ProductStatus;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithCatalog;
use Tests\TestCase;

class PublicMenuTest extends TestCase
{
    use InteractsWithCatalog;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guest_can_view_only_published_products_in_active_categories(): void
    {
        [, $business, $menu] = $this->createCatalogTenant();
        $activeCategory = $this->createCatalogCategory($menu, 'Coffee');
        $inactiveCategory = $menu->categories()->create([
            'name' => 'Hidden',
            'sort_order' => 1,
            'is_active' => false,
        ]);

        $visibleProduct = $this->createCatalogProduct(
            $menu,
            $activeCategory,
            'Espresso',
            status: ProductStatus::Published,
        );
        $this->createCatalogProduct($menu, $activeCategory, 'Draft tea');
        $unavailableProduct = $this->createCatalogProduct(
            $menu,
            $activeCategory,
            'Unavailable latte',
            status: ProductStatus::Published,
        );
        $unavailableProduct->update(['is_available' => false]);
        $this->createCatalogProduct(
            $menu,
            $inactiveCategory,
            'Hidden product',
            status: ProductStatus::Published,
        );

        $menu->update(['status' => MenuStatus::Published]);

        $productQueries = [];

        DB::listen(function (QueryExecuted $query) use (&$productQueries): void {
            $sql = strtolower($query->sql);

            if (str_contains($sql, 'products') && str_contains($sql, 'select')) {
                $productQueries[] = $sql;
            }
        });

        $response = $this->get(route('public.menu.show', [$business->slug, $menu->slug]));

        $this->assertNotEmpty($productQueries);

        foreach ($productQueries as $sql) {
            $this->assertStringContainsString('products"."menu_id', $sql);
        }

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PublicMenu/Show', false)
                ->where('business.name', $business->name)
                ->where('menu.title', $menu->title)
                ->has('categories', 1)
                ->where('categories.0.name', 'Coffee')
                ->has('categories.0.products', 1)
                ->where('categories.0.products.0.name', $visibleProduct->name));
    }

    public function test_guest_cannot_view_a_draft_menu_or_suspended_business(): void
    {
        [, $business, $menu] = $this->createCatalogTenant();

        $this->get(route('public.menu.show', [$business->slug, $menu->slug]))
            ->assertNotFound();

        $menu->update(['status' => MenuStatus::Published]);
        $business->update(['status' => BusinessStatus::Suspended]);

        $this->get(route('public.menu.show', [$business->slug, $menu->slug]))
            ->assertNotFound();
    }
}
