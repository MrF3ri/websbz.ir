<?php

namespace Tests\Feature\PublicMenu;

use App\Enums\BusinessStatus;
use App\Modules\Media\Models\Media;
use App\Modules\Media\Models\ProductImage;
use App\Modules\Menu\Enums\MenuStatus;
use App\Modules\Product\Enums\ProductLabel;
use App\Modules\Product\Enums\ProductStatus;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

    public function test_public_menu_cache_avoids_reloading_catalog_and_is_invalidated_after_product_update(): void
    {
        config(['cache.default' => 'array']);
        Cache::store('array')->flush();

        [$user, $business, $menu] = $this->createCatalogTenant('professional');
        $category = $this->createCatalogCategory($menu, 'Coffee');
        $product = $this->createCatalogProduct(
            $menu,
            $category,
            'Espresso',
            status: ProductStatus::Published,
        );
        $menu->update(['status' => MenuStatus::Published]);

        $url = route('public.menu.show', [$business->slug, $menu->slug]);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('categories.0.products.0.name', 'Espresso'));

        $catalogQueries = [];

        DB::listen(function (QueryExecuted $query) use (&$catalogQueries): void {
            $sql = strtolower($query->sql);

            if (str_contains($sql, 'categories') || str_contains($sql, 'products')) {
                $catalogQueries[] = $sql;
            }
        });

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('categories.0.products.0.name', 'Espresso'));

        $this->assertSame([], $catalogQueries);

        $this->actingAs($user)
            ->put(route('catalog.products.update', $product->public_id), [
                'category_public_id' => $category->public_id,
                'name' => 'Double Espresso',
                'description' => 'Updated description',
                'base_price' => 120000,
                'discount_price' => null,
                'is_available' => true,
                'status' => ProductStatus::Published->value,
            ])
            ->assertRedirect();

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('categories.0.products.0.name', 'Double Espresso')
                ->where('categories.0.products.0.price', 120000));
    }

    public function test_public_menu_displays_allow_listed_product_labels(): void
    {
        [, $business, $menu] = $this->createCatalogTenant('professional');
        $category = $this->createCatalogCategory($menu, 'Coffee');
        $product = $this->createCatalogProduct(
            $menu,
            $category,
            'Chef espresso',
            status: ProductStatus::Published,
        );
        $product->update(['label' => ProductLabel::ChefSpecial]);
        $menu->update(['status' => MenuStatus::Published]);

        $this->get(route('public.menu.show', [$business->slug, $menu->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('categories.0.products.0.label', ProductLabel::ChefSpecial->value));
    }

    public function test_image_slider_entitlement_is_resolved_outside_public_menu_cache(): void
    {
        config(['cache.default' => 'array']);
        Cache::store('array')->flush();

        [, $business, $menu] = $this->createCatalogTenant('professional');
        $category = $this->createCatalogCategory($menu, 'Coffee');
        $product = $this->createCatalogProduct(
            $menu,
            $category,
            'Gallery coffee',
            status: ProductStatus::Published,
        );

        foreach ([1, 2] as $index) {
            $media = Media::query()->create([
                'business_id' => $menu->business_id,
                'disk' => 'media',
                'path' => "products/gallery-{$index}.jpg",
                'mime_type' => 'image/jpeg',
                'size' => 1200,
                'width' => 800,
                'height' => 600,
                'variants' => null,
            ]);

            ProductImage::query()->create([
                'business_id' => $menu->business_id,
                'menu_id' => $menu->getKey(),
                'product_id' => $product->getKey(),
                'media_id' => $media->getKey(),
                'sort_order' => $index,
            ]);
        }

        $menu->update(['status' => MenuStatus::Published]);

        $url = route('public.menu.show', [$business->slug, $menu->slug]);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('menu.image_slider_enabled', true)
                ->has('categories.0.products.0.images', 2));

        $subscription = $business->subscriptions()->latest('id')->firstOrFail();
        $snapshot = $subscription->features_snapshot;
        $snapshot['image_slider'] = false;
        $subscription->update(['features_snapshot' => $snapshot]);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('menu.image_slider_enabled', false)
                ->has('categories.0.products.0.images', 2));
    }

    public function test_top_public_menu_renders_translations_and_exposes_only_entitled_locales(): void
    {
        [, $business, $menu] = $this->createCatalogTenant('top');
        $category = $this->createCatalogCategory($menu, 'قهوه');
        $product = $this->createCatalogProduct(
            $menu,
            $category,
            'اسپرسو',
            status: ProductStatus::Published,
        );
        $menu->translations()->create(['locale' => 'en', 'title' => 'Coffee menu']);
        $category->translations()->create(['locale' => 'en', 'name' => 'Coffee']);
        $product->translations()->create([
            'locale' => 'en',
            'name' => 'Espresso',
            'description' => 'A concentrated coffee drink.',
        ]);
        $menu->update(['status' => MenuStatus::Published]);

        $this->get(route('public.menu.show', [$business->slug, $menu->slug]).'?lang=en')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('menu.title', 'Coffee menu')
                ->where('menu.locale', 'en')
                ->where('menu.direction', 'ltr')
                ->where('menu.source_locale', 'fa')
                ->where('menu.available_locales.0.code', 'fa')
                ->where('menu.available_locales.1.code', 'en')
                ->where('menu.available_locales.2.code', 'ar')
                ->where('categories.0.name', 'Coffee')
                ->where('categories.0.products.0.name', 'Espresso')
                ->where('categories.0.products.0.description', 'A concentrated coffee drink.')
                ->missing('categories.0.translations')
                ->missing('categories.0.products.0.translations'));
    }

    public function test_public_menu_falls_back_per_field_and_ignores_unavailable_or_unsupported_locale(): void
    {
        [, $business, $menu] = $this->createCatalogTenant('professional');
        $category = $this->createCatalogCategory($menu, 'قهوه');
        $product = $this->createCatalogProduct(
            $menu,
            $category,
            'اسپرسو',
            status: ProductStatus::Published,
        );
        $menu->update(['status' => MenuStatus::Published]);

        $this->get(route('public.menu.show', [$business->slug, $menu->slug]).'?lang=en')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('menu.title', $menu->title)
                ->where('menu.locale', 'fa')
                ->where('menu.direction', 'rtl')
                ->has('menu.available_locales', 1)
                ->where('categories.0.name', 'قهوه')
                ->where('categories.0.products.0.name', 'اسپرسو'));

        $this->get(route('public.menu.show', [$business->slug, $menu->slug]).'?lang=xx')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('menu.locale', 'fa')
                ->where('menu.title', $menu->title));
    }

    public function test_public_menu_locale_entitlement_is_rechecked_after_cache_warmup(): void
    {
        config(['cache.default' => 'array']);
        Cache::store('array')->flush();

        [, $business, $menu] = $this->createCatalogTenant('top');
        $category = $this->createCatalogCategory($menu, 'Coffee');
        $this->createCatalogProduct($menu, $category, 'Espresso', status: ProductStatus::Published);
        $menu->translations()->create(['locale' => 'en', 'title' => 'Coffee menu']);
        $menu->update(['status' => MenuStatus::Published]);

        $url = route('public.menu.show', [$business->slug, $menu->slug]).'?lang=en';

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('menu.title', 'Coffee menu')->where('menu.locale', 'en'));

        $subscription = $business->subscriptions()->latest('id')->firstOrFail();
        $snapshot = $subscription->features_snapshot;
        $snapshot['max_languages'] = 1;
        $subscription->update(['features_snapshot' => $snapshot]);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('menu.title', $menu->title)
                ->where('menu.locale', 'fa')
                ->has('menu.available_locales', 1));
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
