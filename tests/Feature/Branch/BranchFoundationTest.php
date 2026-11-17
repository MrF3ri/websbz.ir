<?php

namespace Tests\Feature\Branch;

use App\Modules\Branch\Actions\EnsurePrimaryBranchAction;
use App\Modules\Branch\Models\Branch;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithCatalog;
use Tests\TestCase;

class BranchFoundationTest extends TestCase
{
    use InteractsWithCatalog;
    use RefreshDatabase;

    public function test_primary_branch_initializes_visible_inherited_category_and_product_settings(): void
    {
        [, $business, $menu] = $this->createCatalogTenant();
        $category = $this->createCatalogCategory($menu, 'Hot Drinks', 4);
        $product = $this->createCatalogProduct(
            $menu,
            $category,
            'Americano',
            7,
            125000,
            99000,
        );
        $product->update(['is_available' => false]);

        $branch = app(EnsurePrimaryBranchAction::class)->execute($business);

        $this->assertInstanceOf(Branch::class, $branch);
        $this->assertTrue($branch->is_primary);
        $this->assertSame($business->getKey(), $branch->business_id);
        $this->assertSame($business->getKey(), $branch->primary_business_id);
        $this->assertSame('main', $branch->slug);
        $this->assertSame(26, strlen($branch->public_id));

        $this->assertDatabaseHas('branch_category_settings', [
            'branch_id' => $branch->getKey(),
            'business_id' => $business->getKey(),
            'menu_id' => $menu->getKey(),
            'category_id' => $category->getKey(),
            'is_visible' => true,
            'sort_order' => 4,
        ]);

        $this->assertDatabaseHas('branch_product_settings', [
            'branch_id' => $branch->getKey(),
            'business_id' => $business->getKey(),
            'menu_id' => $menu->getKey(),
            'product_id' => $product->getKey(),
            'name_override' => null,
            'description_override_enabled' => false,
            'base_price_override' => null,
            'discount_override_mode' => 'inherit',
            'is_visible' => true,
            'is_available' => false,
            'track_inventory' => false,
            'stock_quantity' => null,
            'sort_order' => 7,
        ]);
    }

    public function test_primary_branch_initialization_is_idempotent_and_preserves_existing_branch_choices(): void
    {
        [, $business, $menu] = $this->createCatalogTenant();
        $category = $this->createCatalogCategory($menu);
        $product = $this->createCatalogProduct($menu, $category);
        $action = app(EnsurePrimaryBranchAction::class);

        $branch = $action->execute($business);
        $branch->productSettings()->where('product_id', $product->getKey())->update([
            'is_visible' => false,
            'name_override' => 'Branch-specific name',
        ]);

        $again = $action->execute($business);

        $this->assertSame($branch->getKey(), $again->getKey());
        $this->assertSame(1, Branch::query()->where('business_id', $business->getKey())->count());
        $this->assertSame(1, DB::table('branch_category_settings')->where('branch_id', $branch->getKey())->count());
        $this->assertSame(1, DB::table('branch_product_settings')->where('branch_id', $branch->getKey())->count());
        $this->assertDatabaseHas('branch_product_settings', [
            'branch_id' => $branch->getKey(),
            'product_id' => $product->getKey(),
            'is_visible' => false,
            'name_override' => 'Branch-specific name',
        ]);
    }

    public function test_database_rejects_a_primary_branch_without_its_unique_business_marker(): void
    {
        [, $business] = $this->createCatalogTenant();

        $this->expectException(QueryException::class);

        DB::table('branches')->insert([
            'public_id' => (string) \Illuminate\Support\Str::ulid(),
            'business_id' => $business->getKey(),
            'name' => 'Invalid primary',
            'slug' => 'invalid-primary',
            'status' => 'active',
            'is_primary' => true,
            'primary_business_id' => null,
            'timezone' => 'Asia/Tehran',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_database_rejects_branch_category_setting_from_another_business(): void
    {
        [, $businessA, $menuA] = $this->createCatalogTenant();
        [, , $menuB] = $this->createCatalogTenant();
        $categoryB = $this->createCatalogCategory($menuB, 'Foreign Category');
        $branchA = app(EnsurePrimaryBranchAction::class)->execute($businessA);

        $this->expectException(QueryException::class);

        DB::table('branch_category_settings')->insert([
            'branch_id' => $branchA->getKey(),
            'business_id' => $businessA->getKey(),
            'menu_id' => $menuA->getKey(),
            'category_id' => $categoryB->getKey(),
            'is_visible' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_database_rejects_branch_product_setting_from_another_menu(): void
    {
        [, $businessA, $menuA] = $this->createCatalogTenant();
        [, , $menuB] = $this->createCatalogTenant();
        $categoryB = $this->createCatalogCategory($menuB);
        $productB = $this->createCatalogProduct($menuB, $categoryB, 'Foreign Product');
        $branchA = app(EnsurePrimaryBranchAction::class)->execute($businessA);

        $this->expectException(QueryException::class);

        DB::table('branch_product_settings')->insert([
            'branch_id' => $branchA->getKey(),
            'business_id' => $businessA->getKey(),
            'menu_id' => $menuA->getKey(),
            'product_id' => $productB->getKey(),
            'description_override_enabled' => false,
            'discount_override_mode' => 'inherit',
            'is_visible' => true,
            'is_available' => true,
            'track_inventory' => false,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
