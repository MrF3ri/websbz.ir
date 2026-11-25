<?php

namespace Tests\Feature\Branch;

use App\Modules\Branch\Actions\CreateBranchAction;
use App\Modules\Menu\Enums\MenuStatus;
use App\Modules\Product\Enums\ProductStatus;
use App\Modules\PublicMenu\Services\PublicMenuCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithCatalog;
use Tests\TestCase;

class BranchCacheInvalidationTest extends TestCase
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

    public function test_canonical_cache_invalidation_clears_primary_and_every_branch_projection(): void
    {
        [, $business, $menu] = $this->createCatalogTenant('top');
        $branch = app(CreateBranchAction::class)->execute($business, [
            'name' => 'North Branch',
            'slug' => 'north',
        ]);
        $cache = app(PublicMenuCache::class);

        $cache->remember($menu, fn (): string => 'primary-before');
        $cache->remember($menu, fn (): string => 'branch-before', $branch);

        $cache->forget($menu);

        $this->assertSame('primary-after', $cache->remember($menu, fn (): string => 'primary-after'));
        $this->assertSame('branch-after', $cache->remember($menu, fn (): string => 'branch-after', $branch));
    }

    public function test_branch_scoped_invalidation_preserves_primary_menu_cache(): void
    {
        [, $business, $menu] = $this->createCatalogTenant('top');
        $branch = app(CreateBranchAction::class)->execute($business, [
            'name' => 'North Branch',
            'slug' => 'north',
        ]);
        $cache = app(PublicMenuCache::class);

        $cache->remember($menu, fn (): string => 'primary-cached');
        $cache->remember($menu, fn (): string => 'branch-before', $branch);

        $cache->forget($menu, $branch);

        $this->assertSame('primary-cached', $cache->remember($menu, fn (): string => 'primary-after'));
        $this->assertSame('branch-after', $cache->remember($menu, fn (): string => 'branch-after', $branch));
    }

    public function test_canonical_product_update_invalidates_primary_and_branch_public_menu_caches(): void
    {
        [$user, $business, $menu] = $this->createCatalogTenant('top');
        $category = $this->createCatalogCategory($menu, 'Coffee');
        $product = $this->createCatalogProduct(
            $menu,
            $category,
            'Original espresso',
            0,
            120000,
            null,
            ProductStatus::Published,
        );
        $branch = app(CreateBranchAction::class)->execute($business, [
            'name' => 'West Branch',
            'slug' => 'west',
        ]);
        $menu->update(['status' => MenuStatus::Published]);

        $this->get(route('public.menu.show', [$business->slug, $menu->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('categories.0.products.0.name', 'Original espresso'));
        $this->get(route('public.menu.branch.show', [$business->slug, $menu->slug, $branch->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('categories.0.products.0.name', 'Original espresso'));

        $this->actingAs($user)
            ->put(route('catalog.products.update', $product->public_id), [
                'category_public_id' => $category->public_id,
                'name' => 'Updated espresso',
                'description' => null,
                'base_price' => 125000,
                'discount_price' => null,
                'is_available' => true,
                'status' => ProductStatus::Published->value,
            ])
            ->assertRedirect();

        $this->get(route('public.menu.show', [$business->slug, $menu->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('categories.0.products.0.name', 'Updated espresso'));
        $this->get(route('public.menu.branch.show', [$business->slug, $menu->slug, $branch->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('categories.0.products.0.name', 'Updated espresso'));
    }
}
