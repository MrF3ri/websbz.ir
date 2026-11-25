<?php

namespace Tests\Feature\Branch;

use App\Modules\Branch\Actions\CreateBranchAction;
use App\Modules\Branch\Actions\EnsurePrimaryBranchAction;
use App\Modules\Branch\Actions\SetBranchStatusAction;
use App\Modules\Branch\Models\Branch;
use App\Modules\Menu\Enums\MenuStatus;
use App\Modules\Product\Enums\ProductStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithCatalog;
use Tests\TestCase;

class BranchPublicMenuTest extends TestCase
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

    public function test_legacy_public_menu_route_resolves_primary_branch_and_branch_route_resolves_overrides(): void
    {
        [, $business, $menu] = $this->createCatalogTenant('top');
        $category = $this->createCatalogCategory($menu, 'Coffee', 2);
        $product = $this->createCatalogProduct(
            $menu,
            $category,
            'Canonical espresso',
            4,
            120000,
            100000,
            ProductStatus::Published,
        );
        $menu->update(['status' => MenuStatus::Published]);

        $primary = app(EnsurePrimaryBranchAction::class)->execute($business);
        $secondary = app(CreateBranchAction::class)->execute($business, [
            'name' => 'North Branch',
            'slug' => 'north',
        ]);

        $product->translations()->create(['locale' => 'en', 'name' => 'Translated canonical espresso']);

        $secondary->productSettings()->where('product_id', $product->getKey())->update([
            'name_override' => 'North espresso',
            'description_override' => 'North-only description',
            'description_override_enabled' => true,
            'base_price_override' => 145000,
            'discount_override_mode' => 'clear',
            'is_visible' => true,
            'is_available' => true,
            'track_inventory' => false,
            'sort_order' => 1,
        ]);

        $this->get(route('public.menu.show', [$business->slug, $menu->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('branch.slug', $primary->slug)
                ->where('categories.0.products.0.name', 'Canonical espresso')
                ->where('categories.0.products.0.price', 120000)
                ->where('categories.0.products.0.discount_price', 100000));

        $this->get(route('public.menu.branch.show', [$business->slug, $menu->slug, $secondary->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('branch.slug', 'north')
                ->where('categories.0.products.0.name', 'North espresso')
                ->where('categories.0.products.0.description', 'North-only description')
                ->where('categories.0.products.0.price', 145000)
                ->where('categories.0.products.0.discount_price', null));

        $this->get(route('public.menu.branch.show', [$business->slug, $menu->slug, $secondary->slug]).'?lang=en')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('menu.locale', 'en')
                ->where('categories.0.products.0.name', 'North espresso')
                ->where('categories.0.products.0.description', 'North-only description'));

        $this->assertSame('Canonical espresso', $product->fresh()->name);
        $this->assertSame(120000, $product->fresh()->base_price);
    }

    public function test_branch_product_visibility_availability_and_category_visibility_are_applied(): void
    {
        [, $business, $menu] = $this->createCatalogTenant('top');
        $category = $this->createCatalogCategory($menu, 'Coffee');
        $this->createCatalogProduct($menu, $category, 'Visible', status: ProductStatus::Published);
        $hidden = $this->createCatalogProduct($menu, $category, 'Hidden', status: ProductStatus::Published);
        $outOfStock = $this->createCatalogProduct($menu, $category, 'Out of stock', status: ProductStatus::Published);
        $menu->update(['status' => MenuStatus::Published]);

        app(EnsurePrimaryBranchAction::class)->execute($business);
        $branch = app(CreateBranchAction::class)->execute($business, [
            'name' => 'West Branch',
            'slug' => 'west',
        ]);

        $branch->productSettings()->where('product_id', $hidden->getKey())->update(['is_visible' => false]);
        $branch->productSettings()->where('product_id', $outOfStock->getKey())->update([
            'track_inventory' => true,
            'stock_quantity' => 0,
        ]);

        $this->get(route('public.menu.branch.show', [$business->slug, $menu->slug, $branch->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('categories.0.products', 1)
                ->where('categories.0.products.0.name', 'Visible'));

        $branch->categorySettings()->where('category_id', $category->getKey())->update(['is_visible' => false]);

        $this->get(route('public.menu.branch.show', [$business->slug, $menu->slug, $branch->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('categories', 0));
    }

    public function test_foreign_and_inactive_branch_urls_return_not_found_without_primary_fallback(): void
    {
        [, $businessA, $menuA] = $this->createCatalogTenant('top');
        [, $businessB] = $this->createCatalogTenant('top');
        $menuA->update(['status' => MenuStatus::Published]);

        app(EnsurePrimaryBranchAction::class)->execute($businessA);
        $foreignBranch = app(CreateBranchAction::class)->execute($businessB, [
            'name' => 'Foreign Branch',
            'slug' => 'foreign',
        ]);
        $inactiveBranch = app(CreateBranchAction::class)->execute($businessA, [
            'name' => 'Inactive Branch',
            'slug' => 'inactive',
        ]);
        app(SetBranchStatusAction::class)->execute($businessA, $inactiveBranch, false);

        $this->get(route('public.menu.branch.show', [$businessA->slug, $menuA->slug, $foreignBranch->slug]))
            ->assertNotFound();

        $this->get(route('public.menu.branch.show', [$businessA->slug, $menuA->slug, $inactiveBranch->slug]))
            ->assertNotFound();
    }

    public function test_public_menu_cache_is_isolated_between_primary_and_secondary_branches(): void
    {
        [, $business, $menu] = $this->createCatalogTenant('top');
        $category = $this->createCatalogCategory($menu, 'Coffee');
        $product = $this->createCatalogProduct(
            $menu,
            $category,
            'Canonical',
            status: ProductStatus::Published,
        );
        $menu->update(['status' => MenuStatus::Published]);

        app(EnsurePrimaryBranchAction::class)->execute($business);
        $branch = app(CreateBranchAction::class)->execute($business, [
            'name' => 'South Branch',
            'slug' => 'south',
        ]);

        $this->get(route('public.menu.show', [$business->slug, $menu->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('categories.0.products.0.name', 'Canonical'));

        $this->get(route('public.menu.branch.show', [$business->slug, $menu->slug, $branch->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('categories.0.products.0.name', 'Canonical'));

        $branch->productSettings()->where('product_id', $product->getKey())->update([
            'name_override' => 'South-only',
        ]);

        app(\App\Modules\PublicMenu\Services\PublicMenuCache::class)->forget($menu, $branch);

        $this->get(route('public.menu.branch.show', [$business->slug, $menu->slug, $branch->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('categories.0.products.0.name', 'South-only'));

        $this->get(route('public.menu.show', [$business->slug, $menu->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('categories.0.products.0.name', 'Canonical'));
    }
}
