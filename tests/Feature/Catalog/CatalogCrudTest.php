<?php

namespace Tests\Feature\Catalog;

use App\Modules\Category\Models\Category;
use App\Modules\Product\Enums\ProductStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithCatalog;
use Tests\TestCase;

class CatalogCrudTest extends TestCase
{
    use InteractsWithCatalog;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_catalog_page_lazily_ensures_a_primary_menu_for_an_existing_business(): void
    {
        [$user, $business] = $this->createCatalogTenant();

        $business->menu()->delete();
        $this->assertDatabaseCount('menus', 0);

        $this->actingAs($user)
            ->get(route('catalog.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('menu.slug', 'main')
                ->where('menu.default_locale', 'fa'));

        $this->assertDatabaseHas('menus', [
            'business_id' => $business->getKey(),
            'slug' => 'main',
        ]);
    }

    public function test_category_and_product_crud_supports_discount_and_publication_status(): void
    {
        [$user, $business, $menu] = $this->createCatalogTenant('professional');

        $this->actingAs($user)
            ->post(route('catalog.categories.store'), [
                'name' => 'نوشیدنی گرم',
                'is_active' => true,
            ])
            ->assertRedirect();

        $category = $menu->categories()->where('name', 'نوشیدنی گرم')->firstOrFail();

        $this->actingAs($user)
            ->put(route('catalog.categories.update', $category->public_id), [
                'name' => 'قهوه و نوشیدنی',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('catalog.products.store'), [
                'category_public_id' => $category->public_id,
                'name' => 'اسپرسو',
                'description' => 'قهوه تازه',
                'base_price' => 100000,
                'discount_price' => 90000,
                'is_available' => true,
                'status' => ProductStatus::Published->value,
            ])
            ->assertRedirect();

        $product = $menu->products()->where('name', 'اسپرسو')->firstOrFail();

        $this->assertSame($menu->getKey(), $product->menu_id);
        $this->assertSame($category->getKey(), $product->category_id);
        $this->assertSame(100000, $product->base_price);
        $this->assertSame(90000, $product->discount_price);
        $this->assertSame(ProductStatus::Published, $product->status);

        $this->actingAs($user)
            ->put(route('catalog.products.update', $product->public_id), [
                'category_public_id' => $category->public_id,
                'name' => 'اسپرسو دوبل',
                'description' => 'دو شات قهوه',
                'base_price' => 120000,
                'discount_price' => null,
                'is_available' => true,
                'status' => ProductStatus::Draft->value,
                'business_id' => 999999,
                'menu_id' => 999999,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('products', [
            'id' => $product->getKey(),
            'menu_id' => $menu->getKey(),
            'category_id' => $category->getKey(),
            'name' => 'اسپرسو دوبل',
            'base_price' => 120000,
            'discount_price' => null,
            'status' => ProductStatus::Draft->value,
        ]);

        $this->actingAs($user)
            ->get(route('catalog.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('categories.0.name', 'قهوه و نوشیدنی')
                ->where('products.data.0.name', 'اسپرسو دوبل')
                ->where('products.per_page', 20));

        $this->actingAs($user)
            ->delete(route('catalog.products.destroy', $product->public_id))
            ->assertRedirect();

        $this->assertDatabaseMissing('products', ['id' => $product->getKey()]);

        $this->actingAs($user)
            ->delete(route('catalog.categories.destroy', $category->public_id))
            ->assertRedirect();

        $this->assertDatabaseMissing('categories', ['id' => $category->getKey()]);
    }

    public function test_category_with_products_cannot_be_deleted(): void
    {
        [$user, , $menu] = $this->createCatalogTenant();
        $category = $this->createCatalogCategory($menu);
        $product = $this->createCatalogProduct($menu, $category);

        $this->actingAs($user)
            ->delete(route('catalog.categories.destroy', $category->public_id))
            ->assertSessionHasErrors('category');

        $this->assertDatabaseHas('categories', ['id' => $category->getKey()]);
        $this->assertDatabaseHas('products', ['id' => $product->getKey()]);
    }

    public function test_product_search_status_filter_and_twenty_item_pagination_work(): void
    {
        [$user, , $menu] = $this->createCatalogTenant();
        $category = $this->createCatalogCategory($menu);

        for ($index = 1; $index <= 25; $index++) {
            $this->createCatalogProduct(
                $menu,
                $category,
                sprintf('Special Coffee %02d', $index),
                $index,
                100000 + $index,
                null,
                $index <= 5 ? ProductStatus::Published : ProductStatus::Draft,
            );
        }

        $this->actingAs($user)
            ->get(route('catalog.index', [
                'search' => 'Special Coffee',
                'status' => 'published',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('products.total', 5)
                ->where('products.per_page', 20)
                ->where('products.data.0.name', 'Special Coffee 01'));

        $this->actingAs($user)
            ->get(route('catalog.index', ['search' => 'Special Coffee']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('products.total', 25)
                ->where('products.per_page', 20)
                ->where('products.last_page', 2));

        $this->actingAs($user)
            ->get(route('catalog.index', ['search' => 'Coffee 24']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('products.total', 1)
                ->where('products.data.0.name', 'Special Coffee 24'));
    }

    public function test_category_and_product_reordering_rewrites_sort_order_stably(): void
    {
        [$user, , $menu] = $this->createCatalogTenant();

        $firstCategory = $this->createCatalogCategory($menu, 'First', 10);
        $secondCategory = $this->createCatalogCategory($menu, 'Second', 20);

        $this->actingAs($user)
            ->put(route('catalog.categories.reorder'), [
                'category_public_ids' => [$secondCategory->public_id, $firstCategory->public_id],
            ])
            ->assertRedirect();

        $this->assertLessThan(
            $firstCategory->fresh()->sort_order,
            $secondCategory->fresh()->sort_order,
        );

        $firstProduct = $this->createCatalogProduct($menu, $firstCategory, 'First Product', 10);
        $secondProduct = $this->createCatalogProduct($menu, $firstCategory, 'Second Product', 20);

        $this->actingAs($user)
            ->put(route('catalog.products.reorder', $firstCategory->public_id), [
                'product_public_ids' => [$secondProduct->public_id, $firstProduct->public_id],
            ])
            ->assertRedirect();

        $this->assertLessThan(
            $firstProduct->fresh()->sort_order,
            $secondProduct->fresh()->sort_order,
        );
    }
}
