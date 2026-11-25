<?php

namespace Tests\Feature\Menu;

use App\Modules\Menu\Enums\MenuStatus;
use App\Modules\Menu\Services\MenuThemeRegistry;
use App\Modules\Product\Enums\ProductStatus;
use App\Modules\Subscription\Services\EntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

        $registry = app(MenuThemeRegistry::class);

        $this->assertCount(3, $registry->availableFor($economic, new EntitlementService($economic)));
        $this->assertCount(6, $registry->availableFor($professional, new EntitlementService($professional)));
        $this->assertCount(6, $registry->availableFor($top, new EntitlementService($top)));
    }

    public function test_top_plan_can_save_allow_listed_custom_design_tokens(): void
    {
        [$user, , $menu] = $this->createCatalogTenant('top');

        $this->actingAs($user)
            ->put(route('catalog.menu.theme.update'), [
                'template' => 'classic',
                'primary_color' => '#365B4A',
                'font' => 'system',
                'mode' => 'light',
                'density' => 'compact',
                'card_style' => 'elevated',
                'heading_size' => 'display',
            ])
            ->assertRedirect();

        $this->assertSame('compact', $menu->fresh()->theme['density']);
        $this->assertSame('elevated', $menu->fresh()->theme['card_style']);
        $this->assertSame('display', $menu->fresh()->theme['heading_size']);
    }

    public function test_professional_plan_cannot_change_top_only_custom_design_tokens(): void
    {
        [$user, , $menu] = $this->createCatalogTenant('professional');

        $this->actingAs($user)
            ->put(route('catalog.menu.theme.update'), [
                'template' => 'classic',
                'primary_color' => '#365B4A',
                'font' => 'system',
                'mode' => 'light',
                'density' => 'compact',
                'card_style' => 'standard',
                'heading_size' => 'normal',
            ])
            ->assertForbidden();

        $this->assertNull($menu->fresh()->theme);
    }

    public function test_downgraded_top_can_preserve_or_reset_custom_design_but_cannot_change_to_another_custom_value(): void
    {
        [$user, $business, $menu] = $this->createCatalogTenant('top');
        $custom = [
            'template' => 'classic',
            'primary_color' => '#AABBCC',
            'font' => 'serif',
            'mode' => 'dark',
            'density' => 'compact',
            'card_style' => 'elevated',
            'heading_size' => 'display',
        ];

        $this->actingAs($user)
            ->put(route('catalog.menu.theme.update'), $custom)
            ->assertRedirect();

        $subscription = $business->subscriptions()->latest('id')->firstOrFail();
        $snapshot = $subscription->features_snapshot;
        $snapshot['custom_design'] = false;
        $snapshot['custom_colors'] = false;
        $snapshot['font_selection'] = false;
        $snapshot['dark_mode'] = false;
        $subscription->update(['features_snapshot' => $snapshot]);

        $this->actingAs($user)
            ->put(route('catalog.menu.theme.update'), $custom)
            ->assertRedirect();

        $changed = $custom;
        $changed['density'] = 'spacious';

        $this->actingAs($user)
            ->put(route('catalog.menu.theme.update'), $changed)
            ->assertForbidden();

        $this->assertSame('compact', $menu->fresh()->theme['density']);
        $this->assertSame('#AABBCC', $menu->fresh()->theme['primary_color']);
        $this->assertSame('serif', $menu->fresh()->theme['font']);
        $this->assertSame('dark', $menu->fresh()->theme['mode']);

        $reset = $custom;
        $reset['primary_color'] = '#365B4A';
        $reset['font'] = 'system';
        $reset['mode'] = 'light';
        $reset['density'] = 'comfortable';
        $reset['card_style'] = 'standard';
        $reset['heading_size'] = 'normal';

        $this->actingAs($user)
            ->put(route('catalog.menu.theme.update'), $reset)
            ->assertRedirect();

        $this->assertSame('comfortable', $menu->fresh()->theme['density']);
        $this->assertSame('standard', $menu->fresh()->theme['card_style']);
        $this->assertSame('normal', $menu->fresh()->theme['heading_size']);
        $this->assertSame('#365B4A', $menu->fresh()->theme['primary_color']);
        $this->assertSame('system', $menu->fresh()->theme['font']);
        $this->assertSame('light', $menu->fresh()->theme['mode']);
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
            'density' => 'comfortable',
            'card_style' => 'standard',
            'heading_size' => 'normal',
        ], $menu->fresh()->theme);
    }

    public function test_economic_plan_cannot_select_a_template_outside_its_snapshot_limit(): void
    {
        [$user, , $menu] = $this->createCatalogTenant('economic');

        $this->actingAs($user)
            ->put(route('catalog.menu.theme.update'), [
                'template' => 'modern',
                'primary_color' => '#365B4A',
                'font' => 'system',
                'mode' => 'light',
            ])
            ->assertForbidden();

        $this->assertNull($menu->fresh()->theme);
    }

    public function test_economic_plan_can_preserve_a_previously_selected_template(): void
    {
        [$user, , $menu] = $this->createCatalogTenant('economic');
        $menu->update([
            'theme' => [
                'template' => 'editorial',
                'primary_color' => '#365B4A',
                'font' => 'system',
                'mode' => 'light',
            ],
        ]);

        $this->actingAs($user)
            ->put(route('catalog.menu.theme.update'), [
                'template' => 'editorial',
                'primary_color' => '#365B4A',
                'font' => 'system',
                'mode' => 'light',
            ])
            ->assertRedirect();

        $this->assertSame('editorial', $menu->fresh()->theme['template']);
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

    public function test_public_menu_falls_back_for_unknown_custom_design_tokens(): void
    {
        [, $business, $menu] = $this->createCatalogTenant('professional');
        $menu->update([
            'status' => MenuStatus::Published,
            'theme' => [
                'template' => 'classic',
                'primary_color' => '#365B4A',
                'font' => 'system',
                'mode' => 'light',
                'density' => 'untrusted',
                'card_style' => 'arbitrary-css',
                'heading_size' => '<script>',
            ],
        ]);

        $this->get(route('public.menu.show', [$business->slug, $menu->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('menu.theme.density', 'comfortable')
                ->where('menu.theme.card_style', 'standard')
                ->where('menu.theme.heading_size', 'normal'));
    }

    public function test_public_menu_receives_the_saved_allow_list_theme(): void
    {
        [$user, $business, $menu] = $this->createCatalogTenant('professional');
        $category = $this->createCatalogCategory($menu);
        $this->createCatalogProduct(
            $menu,
            $category,
            'Espresso',
            status: ProductStatus::Published,
        );
        $menu->update([
            'status' => MenuStatus::Published,
            'theme' => [
                'template' => 'modern',
                'primary_color' => '#123456',
                'font' => 'rounded',
                'mode' => 'dark',
                'density' => 'spacious',
                'card_style' => 'flat',
                'heading_size' => 'large',
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
                ->where('menu.theme.density', 'spacious')
                ->where('menu.theme.card_style', 'flat')
                ->where('menu.theme.heading_size', 'large')
                ->missing('menu.theme.unsafe_html'));
    }

    public function test_theme_update_invalidates_a_warm_public_menu_cache(): void
    {
        config(['cache.default' => 'array']);
        Cache::store('array')->flush();

        [$user, $business, $menu] = $this->createCatalogTenant('professional');
        $menu->update(['status' => MenuStatus::Published]);
        $url = route('public.menu.show', [$business->slug, $menu->slug]);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('menu.theme.template', 'classic')
                ->where('menu.theme.primary_color', '#365B4A'));

        $this->actingAs($user)
            ->put(route('catalog.menu.theme.update'), [
                'template' => 'modern',
                'primary_color' => '#AABBCC',
                'font' => 'serif',
                'mode' => 'dark',
            ])
            ->assertRedirect();

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('menu.theme.template', 'modern')
                ->where('menu.theme.primary_color', '#AABBCC')
                ->where('menu.theme.font', 'serif')
                ->where('menu.theme.mode', 'dark'));
    }

}
