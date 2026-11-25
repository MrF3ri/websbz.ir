<?php

namespace Tests\Feature\Localization;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCatalog;
use Tests\TestCase;

class TranslationRelationsTest extends TestCase
{
    use InteractsWithCatalog;
    use RefreshDatabase;

    public function test_menu_category_and_product_can_store_one_translation_per_locale(): void
    {
        [, , $menu] = $this->createCatalogTenant('top');
        $category = $this->createCatalogCategory($menu, 'قهوه');
        $product = $this->createCatalogProduct($menu, $category, 'اسپرسو');

        $menu->translations()->create([
            'locale' => 'en',
            'title' => 'Coffee Menu',
        ]);
        $category->translations()->create([
            'locale' => 'en',
            'name' => 'Coffee',
        ]);
        $product->translations()->create([
            'locale' => 'en',
            'name' => 'Espresso',
            'description' => 'A concentrated coffee drink.',
        ]);

        $this->assertSame('Coffee Menu', $menu->translations()->where('locale', 'en')->firstOrFail()->title);
        $this->assertSame('Coffee', $category->translations()->where('locale', 'en')->firstOrFail()->name);
        $this->assertSame('Espresso', $product->translations()->where('locale', 'en')->firstOrFail()->name);
        $this->assertSame(
            'A concentrated coffee drink.',
            $product->translations()->where('locale', 'en')->firstOrFail()->description,
        );
    }
}
