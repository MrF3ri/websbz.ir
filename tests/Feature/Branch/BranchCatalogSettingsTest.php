<?php

namespace Tests\Feature\Branch;

use App\Modules\Branch\Actions\CreateBranchAction;
use App\Modules\Branch\Actions\EnsurePrimaryBranchAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithCatalog;
use Tests\TestCase;

class BranchCatalogSettingsTest extends TestCase
{
    use InteractsWithCatalog;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['cache.default' => 'array']);
        Cache::store('array')->flush();
    }

    public function test_manager_can_edit_branch_category_and_product_settings_without_mutating_canonical_catalog(): void
    {
        [$user, $business, $menu] = $this->createCatalogTenant('top');
        $category = $this->createCatalogCategory($menu, 'Coffee', 2);
        $product = $this->createCatalogProduct(
            $menu,
            $category,
            'Canonical espresso',
            1,
            120000,
            100000,
            ProductStatus::Published,
        );
        app(EnsurePrimaryBranchAction::class)->execute($business);
        $branch = app(CreateBranchAction::class)->execute($business, [
            'name' => 'North Branch',
            'slug' => 'north',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($user)
            ->get(route('business.branches.catalog', $branch->public_id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Branches/Catalog')
                ->where('branch.name', 'North Branch')
                ->where('categories.0.name', 'Coffee')
                ->where('products.0.name', 'Canonical espresso'));

        $response = $this->actingAs($user)->put(
            route('business.branches.catalog.update', $branch->public_id),
            [
                'categories' => [[
                    'public_id' => $category->public_id,
                    'is_visible' => false,
                    'sort_order' => 8,
                ]],
                'products' => [[
                    'public_id' => $product->public_id,
                    'name_override' => 'North espresso',
                    'description_override_enabled' => true,
                    'description_override' => null,
                    'base_price_override' => 145000,
                    'discount_override_mode' => 'clear',
                    'discount_price_override' => null,
                    'is_visible' => true,
                    'is_available' => true,
                    'track_inventory' => true,
                    'stock_quantity' => 0,
                    'sort_order' => 4,
                ]],
            ],
        );

        $response->assertRedirect(route('business.branches.catalog', $branch->public_id));
        $this->assertDatabaseHas('branch_category_settings', [
            'branch_id' => $branch->getKey(),
            'category_id' => $category->getKey(),
            'is_visible' => false,
            'sort_order' => 8,
        ]);
        $this->assertDatabaseHas('branch_product_settings', [
            'branch_id' => $branch->getKey(),
            'product_id' => $product->getKey(),
            'name_override' => 'North espresso',
            'description_override_enabled' => true,
            'description_override' => '',
            'base_price_override' => 145000,
            'discount_override_mode' => 'clear',
            'discount_price_override' => null,
            'track_inventory' => true,
            'stock_quantity' => 0,
            'sort_order' => 4,
        ]);

        $this->assertSame('Canonical espresso', $product->fresh()->name);
        $this->assertSame(120000, $product->fresh()->base_price);
    }

    public function test_branch_product_settings_reject_invalid_effective_discount_and_stock(): void
    {
        [$user, $business, $menu] = $this->createCatalogTenant('top');
        $category = $this->createCatalogCategory($menu, 'Coffee');
        $product = $this->createCatalogProduct($menu, $category, 'Espresso', basePrice: 100000);
        $this->ensurePrimaryBranch($business);
        $branch = app(CreateBranchAction::class)->execute($business, [
            'name' => 'West Branch',
            'slug' => 'west',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($user)->from(route('business.branches.catalog', $branch->public_id))
            ->put(route('business.branches.catalog.update', $branch->public_id), [
                'categories' => [],
                'products' => [[
                    'public_id' => $product->public_id,
                    'name_override' => null,
                    'description_override_enabled' => false,
                    'description_override' => null,
                    'base_price_override' => 90000,
                    'discount_override_mode' => 'set',
                    'discount_price_override' => 90000,
                    'is_visible' => true,
                    'is_available' => true,
                    'track_inventory' => true,
                    'stock_quantity' => null,
                    'sort_order' => 0,
                ]],
            ])
            ->assertSessionHasErrors('products');

        $this->assertDatabaseHas('branch_product_settings', [
            'branch_id' => $branch->getKey(),
            'product_id' => $product->getKey(),
            'base_price_override' => null,
            'discount_override_mode' => 'inherit',
        ]);
    }

    public function test_foreign_category_and_product_ids_are_rejected(): void
    {
        [$user, $businessA, $menuA] = $this->createCatalogTenant('top');
        [, $businessB, $menuB] = $this->createCatalogTenant('top');
        $foreignCategory = $this->createCatalogCategory($menuB, 'Foreign');
        $foreignProduct = $this->createCatalogProduct($menuB, $foreignCategory, 'Foreign item');
        app(EnsurePrimaryBranchAction::class)->execute($businessA);
        $branch = $businessA->primaryBranch()->firstOrFail();
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($user)
            ->put(route('business.branches.catalog.update', $branch->public_id), [
                'categories' => [[
                    'public_id' => $foreignCategory->public_id,
                    'is_visible' => true,
                    'sort_order' => 0,
                ]],
                'products' => [[
                    'public_id' => $foreignProduct->public_id,
                    'name_override' => null,
                    'description_override_enabled' => false,
                    'description_override' => null,
                    'base_price_override' => null,
                    'discount_override_mode' => 'inherit',
                    'discount_price_override' => null,
                    'is_visible' => true,
                    'is_available' => true,
                    'track_inventory' => false,
                    'stock_quantity' => null,
                    'sort_order' => 0,
                ]],
            ])
            ->assertSessionHasErrors('catalog');

        $this->assertDatabaseMissing('branch_product_settings', [
            'branch_id' => $branch->getKey(),
            'product_id' => $foreignProduct->getKey(),
        ]);
    }
}
