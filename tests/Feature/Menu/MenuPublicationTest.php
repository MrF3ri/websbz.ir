<?php

namespace Tests\\Feature\\Menu;

use App\\Modules\\Menu\\Enums\\MenuStatus;
use App\\Modules\\Product\\Enums\\ProductStatus;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Tests\\Concerns\\InteractsWithCatalog;
use Tests\\TestCase;

class MenuPublicationTest extends TestCase
{
    use InteractsWithCatalog;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_menu_cannot_be_published_without_an_active_available_published_product(): void
    {
        [$user, , $menu] = $this->createCatalogTenant();

        $this->actingAs($user)
            ->put(route('catalog.menu.publish'))
            ->assertSessionHasErrors('menu');

        $this->assertSame(MenuStatus::Draft, $menu->fresh()->status);
    }

    public function test_owner_can_publish_and_unpublish_the_primary_menu(): void
    {
        [$user, $business, $menu] = $this->createCatalogTenant();
        $category = $this->createCatalogCategory($menu, 'Coffee');
        $this->createCatalogProduct(
            $menu,
            $category,
            'Espresso',
            status: ProductStatus::Published,
        );

        $this->actingAs($user)
            ->put(route('catalog.menu.publish'))
            ->assertRedirect();

        $this->assertSame(MenuStatus::Published, $menu->fresh()->status);

        $this->get(route('public.menu.show', [$business->slug, $menu->slug]))
            ->assertOk();

        $this->actingAs($user)
            ->put(route('catalog.menu.unpublish'))
            ->assertRedirect();

        $this->assertSame(MenuStatus::Draft, $menu->fresh()->status);

        $this->get(route('public.menu.show', [$business->slug, $menu->slug]))
            ->assertNotFound();
    }
}
