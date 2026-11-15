<?php

namespace Tests\Feature\Catalog;

use App\Models\User;
use App\Modules\Menu\Actions\EnsurePrimaryMenuAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCatalog;
use Tests\TestCase;

class CatalogEntitlementTest extends TestCase
{
    use InteractsWithCatalog;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_economic_twenty_first_product_is_forbidden_and_database_count_is_unchanged(): void
    {
        [$user, , $menu] = $this->createCatalogTenant('economic');
        $category = $this->createCatalogCategory($menu);

        for ($index = 1; $index <= 20; $index++) {
            $this->createCatalogProduct($menu, $category, "Economic Product {$index}", $index);
        }

        $this->actingAs($user)
            ->post(route('catalog.products.store'), [
                'category_public_id' => $category->public_id,
                'name' => 'Economic Product 21',
                'base_price' => 100000,
                'status' => 'draft',
            ])
            ->assertForbidden();

        $this->assertSame(20, $menu->products()->count());
    }

    public function test_economic_third_category_is_forbidden(): void
    {
        [$user, , $menu] = $this->createCatalogTenant('economic');
        $this->createCatalogCategory($menu, 'First', 0);
        $this->createCatalogCategory($menu, 'Second', 1);

        $this->actingAs($user)
            ->post(route('catalog.categories.store'), ['name' => 'Third'])
            ->assertForbidden();

        $this->assertSame(2, $menu->categories()->count());
    }

    public function test_professional_sixth_category_is_forbidden(): void
    {
        [$user, , $menu] = $this->createCatalogTenant('professional');

        for ($index = 1; $index <= 5; $index++) {
            $this->createCatalogCategory($menu, "Category {$index}", $index);
        }

        $this->actingAs($user)
            ->post(route('catalog.categories.store'), ['name' => 'Category 6'])
            ->assertForbidden();

        $this->assertSame(5, $menu->categories()->count());
    }

    public function test_top_plan_has_unlimited_categories_and_products(): void
    {
        [$user, , $menu] = $this->createCatalogTenant('top');

        for ($index = 1; $index <= 8; $index++) {
            $this->actingAs($user)
                ->post(route('catalog.categories.store'), ['name' => "Top Category {$index}"])
                ->assertRedirect();
        }

        $category = $menu->categories()->firstOrFail();

        for ($index = 1; $index <= 25; $index++) {
            $this->actingAs($user)
                ->post(route('catalog.products.store'), [
                    'category_public_id' => $category->public_id,
                    'name' => "Top Product {$index}",
                    'base_price' => 100000,
                    'status' => 'draft',
                ])
                ->assertRedirect();
        }

        $this->assertSame(8, $menu->categories()->count());
        $this->assertSame(25, $menu->products()->count());
    }

    public function test_economic_discount_and_availability_toggle_are_forbidden(): void
    {
        [$user, , $menu] = $this->createCatalogTenant('economic');
        $category = $this->createCatalogCategory($menu);

        $payload = [
            'category_public_id' => $category->public_id,
            'name' => 'Discounted Product',
            'base_price' => 100000,
            'discount_price' => 90000,
            'status' => 'draft',
        ];

        $this->actingAs($user)
            ->post(route('catalog.products.store'), $payload)
            ->assertForbidden();

        unset($payload['discount_price']);
        $payload['name'] = 'Unavailable Product';
        $payload['is_available'] = false;

        $this->actingAs($user)
            ->post(route('catalog.products.store'), $payload)
            ->assertForbidden();

        $this->assertSame(0, $menu->products()->count());
    }

    public function test_business_without_subscription_and_business_with_expired_subscription_receive_403(): void
    {
        $userWithoutSubscription = User::factory()->create(['email_verified_at' => now()]);
        $businessWithoutSubscription = $this->createBusinessFor($userWithoutSubscription);
        $menuWithoutSubscription = app(EnsurePrimaryMenuAction::class)
            ->execute($businessWithoutSubscription);

        $this->actingAs($userWithoutSubscription)
            ->post(route('catalog.categories.store'), ['name' => 'No Subscription'])
            ->assertForbidden();

        [$expiredUser, , $expiredMenu] = $this->createCatalogTenant('professional');
        $subscription = $expiredMenu->business->subscriptions()->firstOrFail();
        $subscription->update([
            'current_period_ends_at' => now()->subDay(),
            'trial_ends_at' => now()->subDay(),
        ]);

        $this->actingAs($expiredUser)
            ->post(route('catalog.categories.store'), ['name' => 'Expired Plan'])
            ->assertForbidden();

        $this->assertSame(0, $menuWithoutSubscription->categories()->count());
        $this->assertSame(0, $expiredMenu->categories()->count());
    }

    public function test_product_usage_is_independent_per_business(): void
    {
        [$userA, , $menuA] = $this->createCatalogTenant('economic');
        [$userB, , $menuB] = $this->createCatalogTenant('economic');
        $categoryA = $this->createCatalogCategory($menuA);
        $categoryB = $this->createCatalogCategory($menuB);

        for ($index = 1; $index <= 20; $index++) {
            $this->createCatalogProduct($menuA, $categoryA, "Business A {$index}", $index);
        }

        $this->actingAs($userB)
            ->post(route('catalog.products.store'), [
                'category_public_id' => $categoryB->public_id,
                'name' => 'Business B First Product',
                'base_price' => 100000,
                'status' => 'draft',
            ])
            ->assertRedirect();

        $this->assertSame(20, $menuA->products()->count());
        $this->assertSame(1, $menuB->products()->count());
    }
}
