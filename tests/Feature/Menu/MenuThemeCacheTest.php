<?php

namespace Tests\Feature\Menu;

use App\Modules\Menu\Enums\MenuStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithCatalog;
use Tests\TestCase;

class MenuThemeCacheTest extends TestCase
{
    use InteractsWithCatalog;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
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
