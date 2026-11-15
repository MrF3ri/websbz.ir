<?php

namespace Tests\Feature\Catalog;

use App\Enums\BusinessRole;
use App\Models\User;
use App\Modules\Business\Models\Business;
use App\Modules\Category\Models\Category;
use App\Modules\Menu\Actions\EnsurePrimaryMenuAction;
use App\Modules\Tenancy\Services\CurrentBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\InteractsWithCatalog;
use Tests\TestCase;

class CatalogTenantIsolationTest extends TestCase
{
    use InteractsWithCatalog;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_user_a_cannot_list_update_delete_or_reorder_business_b_catalog_records(): void
    {
        [$userA, , $menuA] = $this->createCatalogTenant();
        [$userB, , $menuB] = $this->createCatalogTenant();

        $categoryA = $this->createCatalogCategory($menuA, 'A Private Category');
        $categoryB = $this->createCatalogCategory($menuB, 'B Private Category');
        $productA = $this->createCatalogProduct($menuA, $categoryA, 'A Private Product');
        $productB = $this->createCatalogProduct($menuB, $categoryB, 'B Private Product');

        $this->actingAs($userA)
            ->get(route('catalog.index'))
            ->assertOk()
            ->assertSee('A Private Category')
            ->assertSee('A Private Product')
            ->assertDontSee('B Private Category')
            ->assertDontSee('B Private Product');

        $this->actingAs($userA)
            ->put(route('catalog.categories.update', $categoryB->public_id), [
                'name' => 'Stolen Category',
            ])
            ->assertNotFound();

        $this->actingAs($userA)
            ->delete(route('catalog.categories.destroy', $categoryB->public_id))
            ->assertNotFound();

        $this->actingAs($userA)
            ->put(route('catalog.products.update', $productB->public_id), [
                'category_public_id' => $categoryA->public_id,
                'name' => 'Stolen Product',
                'base_price' => 1,
                'status' => 'draft',
            ])
            ->assertNotFound();

        $this->actingAs($userA)
            ->delete(route('catalog.products.destroy', $productB->public_id))
            ->assertNotFound();

        $this->actingAs($userA)
            ->put(route('catalog.categories.reorder'), [
                'category_public_ids' => [$categoryA->public_id, $categoryB->public_id],
            ])
            ->assertSessionHasErrors('category_public_ids');

        $this->actingAs($userA)
            ->put(route('catalog.products.reorder', $categoryA->public_id), [
                'product_public_ids' => [$productA->public_id, $productB->public_id],
            ])
            ->assertSessionHasErrors('product_public_ids');

        $this->assertDatabaseHas('categories', ['id' => $categoryB->getKey(), 'name' => 'B Private Category']);
        $this->assertDatabaseHas('products', ['id' => $productB->getKey(), 'name' => 'B Private Product']);
    }

    public function test_swapped_public_ids_and_foreign_menu_categories_are_rejected(): void
    {
        [$userA, , $menuA] = $this->createCatalogTenant();
        [, , $menuB] = $this->createCatalogTenant();

        $categoryA = $this->createCatalogCategory($menuA, 'A Category');
        $categoryB = $this->createCatalogCategory($menuB, 'B Category');
        $productA = $this->createCatalogProduct($menuA, $categoryA, 'A Product');

        $this->actingAs($userA)
            ->put(route('catalog.products.update', $productA->public_id), [
                'category_public_id' => $categoryB->public_id,
                'name' => 'Swapped Category Product',
                'base_price' => 1000,
                'status' => 'draft',
            ])
            ->assertSessionHasErrors('category_public_id');

        $this->actingAs($userA)
            ->post(route('catalog.products.store'), [
                'category_public_id' => $categoryB->public_id,
                'name' => 'Foreign Category Product',
                'base_price' => 1000,
                'status' => 'draft',
            ])
            ->assertSessionHasErrors('category_public_id');
    }

    public function test_injected_business_id_and_menu_id_are_ignored_for_category_and_product_writes(): void
    {
        [$userA, $businessA, $menuA] = $this->createCatalogTenant();
        [, $businessB, $menuB] = $this->createCatalogTenant();

        $this->actingAs($userA)
            ->post(route('catalog.categories.store'), [
                'name' => 'Tenant A Category',
                'business_id' => $businessB->getKey(),
                'menu_id' => $menuB->getKey(),
            ])
            ->assertRedirect();

        $category = $menuA->categories()->where('name', 'Tenant A Category')->firstOrFail();

        $this->actingAs($userA)
            ->post(route('catalog.products.store'), [
                'category_public_id' => $category->public_id,
                'name' => 'Tenant A Product',
                'base_price' => 1000,
                'status' => 'draft',
                'business_id' => $businessB->getKey(),
                'menu_id' => $menuB->getKey(),
            ])
            ->assertRedirect();

        $product = $menuA->products()->where('name', 'Tenant A Product')->firstOrFail();

        $this->assertSame($businessA->getKey(), $menuA->business_id);
        $this->assertSame($menuA->getKey(), $product->menu_id);
        $this->assertSame($category->getKey(), $product->category_id);
        $this->assertDatabaseMissing('products', [
            'id' => $product->getKey(),
            'menu_id' => $menuB->getKey(),
        ]);
    }

    public function test_owner_admin_manager_and_editor_can_manage_catalog_but_non_members_are_denied(): void
    {
        foreach ([
            BusinessRole::Owner,
            BusinessRole::Admin,
            BusinessRole::Manager,
            BusinessRole::Editor,
        ] as $role) {
            [$user] = $this->createCatalogTenant('professional', $role);

            $this->actingAs($user)
                ->get(route('catalog.index'))
                ->assertOk();

            $this->actingAs($user)
                ->post(route('catalog.categories.store'), ['name' => "Category {$role->value}"])
                ->assertRedirect();
        }

        [$owner, $business] = $this->createCatalogTenant();
        $outsider = User::factory()->create(['email_verified_at' => now()]);
        app(CurrentBusiness::class)->set($business);

        $this->assertFalse(Gate::forUser($outsider)->allows('viewAny', Category::class));
        $this->assertFalse(Gate::forUser($outsider)->allows('viewAny', Product::class));
        $this->assertTrue($business->members()->where('user_id', $owner->getKey())->exists());
    }

    public function test_primary_menu_action_is_idempotent_for_existing_businesses(): void
    {
        $business = Business::factory()->create();

        $first = app(EnsurePrimaryMenuAction::class)->execute($business);
        $second = app(EnsurePrimaryMenuAction::class)->execute($business);

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame('main', $first->slug);
        $this->assertSame('fa', $first->default_locale);
        $this->assertDatabaseCount('menus', 1);
    }
}
