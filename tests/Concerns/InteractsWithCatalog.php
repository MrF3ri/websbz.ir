<?php

namespace Tests\Concerns;

use App\Enums\BusinessRole;
use App\Models\User;
use App\Modules\Branch\Actions\EnsurePrimaryBranchAction;
use App\Modules\Business\Models\Business;
use App\Modules\Category\Models\Category;
use App\Modules\Menu\Actions\EnsurePrimaryMenuAction;
use App\Modules\Menu\Models\Menu;
use App\Modules\Product\Enums\ProductStatus;
use App\Modules\Product\Models\Product;

trait InteractsWithCatalog
{
    use InteractsWithBusinesses;

    /**
     * @return array{0: User, 1: Business, 2: Menu}
     */
    protected function createCatalogTenant(
        string $planCode = 'professional',
        BusinessRole $role = BusinessRole::Owner,
    ): array {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = $this->createBusinessFor($user, $role);
        $this->giveBusinessTrialSubscription($business, $planCode);
        $menu = app(EnsurePrimaryMenuAction::class)->execute($business);
        app(EnsurePrimaryBranchAction::class)->execute($business);

        return [$user, $business, $menu];
    }

    protected function createCatalogCategory(
        Menu $menu,
        string $name = 'Coffee',
        int $sortOrder = 0,
    ): Category {
        return $menu->categories()->create([
            'name' => $name,
            'sort_order' => $sortOrder,
            'is_active' => true,
        ]);
    }

    protected function createCatalogProduct(
        Menu $menu,
        Category $category,
        string $name = 'Espresso',
        int $sortOrder = 0,
        int $basePrice = 100000,
        ?int $discountPrice = null,
        ProductStatus $status = ProductStatus::Draft,
    ): Product {
        return $category->products()->create([
            'menu_id' => $menu->getKey(),
            'name' => $name,
            'base_price' => $basePrice,
            'discount_price' => $discountPrice,
            'status' => $status,
            'sort_order' => $sortOrder,
        ]);
    }
}
