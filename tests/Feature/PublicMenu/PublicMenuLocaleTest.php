<?php

namespace Tests\Feature\PublicMenu;

use App\Modules\Menu\Enums\MenuStatus;
use App\Modules\Product\Enums\ProductStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithCatalog;
use Tests\TestCase;

class PublicMenuLocaleTest extends TestCase
{
    use InteractsWithCatalog;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_top_public_menu_renders_translations_and_available_locale_metadata(): void
    {
        [, $business, $menu] = $this->createCatalogTenant('top');
        $category = $this->createCatalogCategory($menu, 'قهوه');
        $product = $this->createCatalogProduct(
            $menu,
            $category,
            'اسپرسو',
            status: ProductStatus::Published,
        );
        $menu->update(['status' => MenuStatus::Published]);

        $menu->translations()->create(['locale' => 'en', 'title' => 'Coffee Menu']);
        $category->translations()->create(['locale' => 'en', 'name' => 'Coffee']);
        $product->translations()->create([
            'locale' => 'en',
            'name' => 'Espresso',
            'description' => 'A concentrated coffee drink.',
        ]);

        $this->get(route('public.menu.show', [$business->slug, $menu->slug]).'?lang=en')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('menu.title', 'Coffee Menu')
                ->where('menu.locale', 'en')
                ->where('menu.direction', 'ltr')
                ->where('locales.0.code', 'fa')
                ->where('locales.1.code', 'en')
                ->where('locales.2.code', 'ar')
                ->where('categories.0.name', 'Coffee')
                ->where('categories.0.products.0.name', 'Espresso')
                ->where('categories.0.products.0.description', 'A concentrated coffee drink.'));
    }

    public function test_missing_translation_fields_fall_back_to_source_text(): void
    {
        [, $business, $menu] = $this->createCatalogTenant('top');
        $category = $this->createCatalogCategory($menu, 'قهوه');
        $this->createCatalogProduct($menu, $category, 'اسپرسو', status: ProductStatus::Published);
        $menu->update(['status' => MenuStatus::Published]);

        $this->get(route('public.menu.show', [$business->slug, $menu->slug]).'?lang=en')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('menu.title', $menu->title)
                ->where('menu.locale', 'en')
                ->where('menu.direction', 'ltr')
                ->where('categories.0.name', $category->name)
                ->where('categories.0.products.0.name', 'اسپرسو'));
    }

    public function test_single_language_plan_and_unknown_locale_fall_back_to_default(): void
    {
        [, $business, $menu] = $this->createCatalogTenant('professional');
        $menu->update(['status' => MenuStatus::Published]);
        $url = route('public.menu.show', [$business->slug, $menu->slug]);

        $this->get($url.'?lang=en')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('menu.locale', 'fa')
                ->where('menu.direction', 'rtl')
                ->has('locales', 1));

        [, $topBusiness, $topMenu] = $this->createCatalogTenant('top');
        $topMenu->update(['status' => MenuStatus::Published]);

        $this->get(route('public.menu.show', [$topBusiness->slug, $topMenu->slug]).'?lang=fr')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('menu.locale', 'fa')
                ->where('menu.direction', 'rtl'));
    }

    public function test_translation_update_invalidates_the_locale_specific_public_menu_cache(): void
    {
        [$user, $business, $menu] = $this->createCatalogTenant('top');
        $category = $this->createCatalogCategory($menu, 'قهوه');
        $product = $this->createCatalogProduct($menu, $category, 'اسپرسو', status: ProductStatus::Published);
        $menu->update(['status' => MenuStatus::Published]);
        $menu->translations()->create(['locale' => 'en', 'title' => 'Old title']);
        $category->translations()->create(['locale' => 'en', 'name' => 'Old category']);
        $product->translations()->create(['locale' => 'en', 'name' => 'Old product']);

        $url = route('public.menu.show', [$business->slug, $menu->slug]).'?lang=en';

        $this->get($url)
            ->assertInertia(fn (Assert $page) => $page->where('menu.title', 'Old title'));

        $this->actingAs($user)
            ->put(route('catalog.menu.translations.update', 'en'), [
                'menu_title' => 'New title',
                'categories' => [
                    ['public_id' => $category->public_id, 'name' => 'New category'],
                ],
                'products' => [
                    [
                        'public_id' => $product->public_id,
                        'name' => 'New product',
                        'description' => null,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->get($url)
            ->assertInertia(fn (Assert $page) => $page
                ->where('menu.title', 'New title')
                ->where('categories.0.name', 'New category')
                ->where('categories.0.products.0.name', 'New product'));
    }
}
