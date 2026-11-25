<?php

namespace Tests\Feature\Menu;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithCatalog;
use Tests\TestCase;

class MenuTranslationsTest extends TestCase
{
    use InteractsWithCatalog;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_top_can_view_translation_editor_for_enabled_locales(): void
    {
        [$user, , $menu] = $this->createCatalogTenant('top');

        $this->actingAs($user)
            ->get(route('catalog.menu.translations.edit', 'en'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Menu/Translations')
                ->where('locale.code', 'en')
                ->where('locale.direction', 'ltr')
                ->where('locales.0.code', 'en')
                ->where('locales.1.code', 'ar')
                ->where('menu.source_title', $menu->title));
    }

    public function test_top_can_save_menu_category_and_product_translations_atomically(): void
    {
        [$user, , $menu] = $this->createCatalogTenant('top');
        $category = $this->createCatalogCategory($menu, 'قهوه');
        $product = $this->createCatalogProduct($menu, $category, 'اسپرسو');

        $this->actingAs($user)
            ->put(route('catalog.menu.translations.update', 'en'), [
                'menu_title' => 'Coffee Menu',
                'categories' => [
                    ['public_id' => $category->public_id, 'name' => 'Coffee'],
                ],
                'products' => [
                    [
                        'public_id' => $product->public_id,
                        'name' => 'Espresso',
                        'description' => 'A concentrated coffee drink.',
                    ],
                ],
            ])
            ->assertRedirect(route('catalog.menu.translations.edit', 'en'));

        $this->assertDatabaseHas('menu_translations', [
            'menu_id' => $menu->getKey(),
            'locale' => 'en',
            'title' => 'Coffee Menu',
        ]);
        $this->assertDatabaseHas('category_translations', [
            'category_id' => $category->getKey(),
            'locale' => 'en',
            'name' => 'Coffee',
        ]);
        $this->assertDatabaseHas('product_translations', [
            'product_id' => $product->getKey(),
            'locale' => 'en',
            'name' => 'Espresso',
            'description' => 'A concentrated coffee drink.',
        ]);
    }

    public function test_single_language_plan_cannot_view_or_update_translation_locales(): void
    {
        [$user] = $this->createCatalogTenant('professional');

        $this->actingAs($user)
            ->get(route('catalog.menu.translations.edit', 'en'))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('catalog.menu.translations.update', 'ar'), [
                'menu_title' => 'Arabic menu',
                'categories' => [],
                'products' => [],
            ])
            ->assertForbidden();
    }

    public function test_default_locale_cannot_be_saved_as_an_alternate_translation(): void
    {
        [$user] = $this->createCatalogTenant('top');

        $this->actingAs($user)
            ->get(route('catalog.menu.translations.edit', 'fa'))
            ->assertForbidden();
    }

    public function test_translation_editor_rejects_blank_required_text_without_persisting_partial_rows(): void
    {
        [$user, , $menu] = $this->createCatalogTenant('top');
        $category = $this->createCatalogCategory($menu, 'قهوه');
        $product = $this->createCatalogProduct($menu, $category, 'اسپرسو');

        $this->actingAs($user)
            ->put(route('catalog.menu.translations.update', 'en'), [
                'menu_title' => ' ',
                'categories' => [
                    ['public_id' => $category->public_id, 'name' => ' '],
                ],
                'products' => [
                    [
                        'public_id' => $product->public_id,
                        'name' => '',
                        'description' => 'Optional description',
                    ],
                ],
            ])
            ->assertSessionHasErrors([
                'menu_title',
                'categories.0.name',
                'products.0.name',
            ]);

        $this->assertDatabaseMissing('menu_translations', [
            'menu_id' => $menu->getKey(),
            'locale' => 'en',
        ]);
        $this->assertDatabaseMissing('category_translations', [
            'category_id' => $category->getKey(),
            'locale' => 'en',
        ]);
        $this->assertDatabaseMissing('product_translations', [
            'product_id' => $product->getKey(),
            'locale' => 'en',
        ]);
    }

    public function test_translation_update_rejects_category_and_product_ids_from_another_menu(): void
    {
        [$userA, , $menuA] = $this->createCatalogTenant('top');
        [, , $menuB] = $this->createCatalogTenant('top');
        $categoryA = $this->createCatalogCategory($menuA, 'قهوه A');
        $productA = $this->createCatalogProduct($menuA, $categoryA, 'اسپرسو A');
        $categoryB = $this->createCatalogCategory($menuB, 'قهوه B');
        $productB = $this->createCatalogProduct($menuB, $categoryB, 'اسپرسو B');

        $this->actingAs($userA)
            ->put(route('catalog.menu.translations.update', 'en'), [
                'menu_title' => 'Foreign menu',
                'categories' => [
                    ['public_id' => $categoryB->public_id, 'name' => 'Foreign category'],
                ],
                'products' => [
                    [
                        'public_id' => $productB->public_id,
                        'name' => 'Foreign product',
                        'description' => 'Not allowed.',
                    ],
                ],
            ])
            ->assertSessionHasErrors(['categories', 'products']);

        $this->assertDatabaseMissing('menu_translations', [
            'menu_id' => $menuA->getKey(),
            'locale' => 'en',
        ]);
        $this->assertDatabaseMissing('category_translations', [
            'category_id' => $categoryA->getKey(),
            'locale' => 'en',
        ]);
        $this->assertDatabaseMissing('product_translations', [
            'product_id' => $productA->getKey(),
            'locale' => 'en',
        ]);
    }
}
