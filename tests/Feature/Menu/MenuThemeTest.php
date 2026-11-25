<?php

namespace Tests\Feature\Menu;

use App\Modules\Menu\Services\MenuThemeRegistry;
use App\Modules\Subscription\Services\EntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithCatalog;
use Tests\TestCase;

class MenuThemeTest extends TestCase
{
    use InteractsWithCatalog;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_template_registry_uses_the_subscription_snapshot(): void
    {
        [, $economic] = $this->createCatalogTenant('economic');
        [, $professional] = $this->createCatalogTenant('professional');
        [, $top] = $this->createCatalogTenant('top');

        $registry = new MenuThemeRegistry();

        $this->assertCount(3, $registry->availableFor($economic, new EntitlementService($economic)));
        $this->assertCount(6, $registry->availableFor($professional, new EntitlementService($professional)));
        $this->assertCount(6, $registry->availableFor($top, new EntitlementService($top)));
    }

    public function test_business_owner_can_save_a_theme_with_plan_entitled_settings(): void
    {
        [$user, , $menu] = $this->createCatalogTenant('professional');

        $this->actingAs($user)
            ->put(route('catalog.menu.theme.update'), [
                'template' => 'editorial',
                'primary_color' => '#AABBCC',
                'font' => 'serif',
                'mode' => 'dark',
            ])
            ->assertRedirect();

        $this->assertSame([
            'template' => 'editorial',
            'primary_color' => '#AABBCC',
            'font' => 'serif',
            'mode' => 'dark',
        ], $menu->fresh()->theme);
    }

    public function test_economic_plan_cannot_select_a_template_outside_its_snapshot_limit(): void
    {
        [$user, , $menu] = $this->createCatalogTenant('economic');

        $this->actingAs($user)
            ->put(route('catalog.menu.theme.update'), [
                'template' => 'editorial',
                'primary_color' => '#365B4A',
                'font' => 'system',
                'mode' => 'light',
            ])
            ->assertForbidden();

        $this->assertNull($menu->fresh()->theme);
    }

    public function test_economic_plan_cannot_change_custom_colors_or_font(): void
    {
        [$user] = $this->createCatalogTenant('economic');

        $this->actingAs($user)
            ->put(route('catalog.menu.theme.update'), [
                'template' => 'classic',
                'primary_color' => '#AABBCC',
                'font' => 'system',
                'mode' => 'light',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('catalog.menu.theme.update'), [
                'template' => 'classic',
                'primary_color' => '#365B4A',
                'font' => 'serif',
                'mode' => 'light',
            ])
            ->assertForbidden();
    }

    public function test_public_menu_receives_the_saved_allow_list_theme(): void
    {
        [$user, $business, $menu] = $this->createCatalogTenant('professional');
        $category = $this->createCatalogCategory($menu);
        $this->createCatalogProduct(
            $menu,
            $category,
            'Espresso',
            status: \App\Modules\Product\Enums\ProductStatus::Published,
        );
        $menu->update([
            'status' => \App\Modules\Menu\Enums\MenuStatus::Published,
            'theme' => [
                'template' => 'modern',
                'primary_color' => '#123456',
                'font' => 'rounded',
                'mode' => 'dark',
                'unsafe_html' => '<script>alert(1)</script>',
            ],
        ]);

        $this->actingAs($user)
            ->get(route('public.menu.show', [$business->slug, $menu->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PublicMenu/Show')
                ->where('menu.theme.template', 'modern')
                ->where('menu.theme.primary_color', '#123456')
                ->where('menu.theme.font', 'rounded')
                ->where('menu.theme.mode', 'dark')
                ->missing('menu.theme.unsafe_html'));
    }
}
