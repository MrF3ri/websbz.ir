<?php

namespace Tests\Feature\Branch;

use App\Modules\Branch\Actions\CreateBranchAction;
use App\Modules\Branch\Actions\SetBranchStatusAction;
use App\Modules\Category\Actions\CreateCategoryAction;
use App\Modules\Product\Actions\CreateProductAction;
use App\Modules\Product\Enums\ProductStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\Concerns\InteractsWithCatalog;
use Tests\TestCase;

class BranchLifecycleTest extends TestCase
{
    use InteractsWithCatalog;
    use RefreshDatabase;

    public function test_top_plan_can_create_branch_with_inherited_catalog_assignments(): void
    {
        [, $business, $menu] = $this->createCatalogTenant('top');
        $category = $this->createCatalogCategory($menu, 'Breakfast', 3);
        $product = $this->createCatalogProduct($menu, $category, 'Omelette', 2, 180000, 150000);

        $branch = app(CreateBranchAction::class)->execute($business, [
            'name' => 'North Branch',
            'slug' => 'north',
        ]);

        $this->assertSame('active', $branch->status);
        $this->assertFalse($branch->is_primary);
        $this->assertDatabaseHas('branch_category_settings', [
            'branch_id' => $branch->getKey(),
            'category_id' => $category->getKey(),
            'is_visible' => true,
            'sort_order' => 3,
        ]);
        $this->assertDatabaseHas('branch_product_settings', [
            'branch_id' => $branch->getKey(),
            'product_id' => $product->getKey(),
            'name_override' => null,
            'base_price_override' => null,
            'discount_override_mode' => 'inherit',
            'is_visible' => true,
            'is_available' => true,
            'track_inventory' => false,
            'sort_order' => 2,
        ]);
    }

    public function test_professional_plan_cannot_create_a_second_branch(): void
    {
        [, $business] = $this->createCatalogTenant('professional');

        $this->expectException(AccessDeniedHttpException::class);

        app(CreateBranchAction::class)->execute($business, [
            'name' => 'Second Branch',
            'slug' => 'second',
        ]);
    }

    public function test_new_categories_and_products_are_assigned_to_each_active_branch(): void
    {
        [, $business, $menu] = $this->createCatalogTenant('top');
        $branch = app(CreateBranchAction::class)->execute($business, [
            'name' => 'West Branch',
            'slug' => 'west',
        ]);

        $category = app(CreateCategoryAction::class)->execute($business, $menu, 'Desserts');
        $product = app(CreateProductAction::class)->execute(
            $business,
            $menu,
            $category,
            [
                'name' => 'Cheesecake',
                'description' => null,
                'base_price' => 210000,
                'discount_price' => null,
                'is_available' => true,
                'status' => ProductStatus::Draft,
            ],
        );

        foreach ([$business->primaryBranch()->firstOrFail(), $branch] as $activeBranch) {
            $this->assertDatabaseHas('branch_category_settings', [
                'branch_id' => $activeBranch->getKey(),
                'category_id' => $category->getKey(),
                'is_visible' => true,
            ]);
            $this->assertDatabaseHas('branch_product_settings', [
                'branch_id' => $activeBranch->getKey(),
                'product_id' => $product->getKey(),
                'is_visible' => true,
                'is_available' => true,
                'discount_override_mode' => 'inherit',
            ]);
        }
    }

    public function test_new_catalog_items_do_not_reactivate_or_repopulate_an_inactive_branch(): void
    {
        [, $business, $menu] = $this->createCatalogTenant('top');
        $branch = app(CreateBranchAction::class)->execute($business, [
            'name' => 'East Branch',
            'slug' => 'east',
        ]);
        app(SetBranchStatusAction::class)->execute($business, $branch, false);

        $category = app(CreateCategoryAction::class)->execute($business, $menu, 'Seasonal');

        $this->assertDatabaseMissing('branch_category_settings', [
            'branch_id' => $branch->getKey(),
            'category_id' => $category->getKey(),
        ]);
    }

    public function test_primary_branch_cannot_be_deactivated(): void
    {
        [, $business] = $this->createCatalogTenant('top');
        $primary = $business->primaryBranch()->firstOrFail();

        try {
            app(SetBranchStatusAction::class)->execute($business, $primary, false);
            $this->fail('The primary branch must remain active.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('branch', $exception->errors());
        }

        $this->assertSame('active', $primary->fresh()->status);
    }

    public function test_branch_from_another_business_cannot_be_updated_through_the_current_tenant(): void
    {
        [, $businessA] = $this->createCatalogTenant('top');
        [, $businessB] = $this->createCatalogTenant('top');
        $branchB = app(CreateBranchAction::class)->execute($businessB, [
            'name' => 'Private Branch',
            'slug' => 'private',
        ]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        app(\App\Modules\Branch\Actions\UpdateBranchAction::class)->execute(
            $businessA,
            $branchB,
            ['name' => 'Intrusion', 'slug' => 'intrusion'],
        );
    }
}
