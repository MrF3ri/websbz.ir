<?php

namespace Tests\Feature\Menu;

use App\Modules\Menu\Enums\MenuStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithCatalog;
use Tests\TestCase;

class MenuBannerTest extends TestCase
{
    use InteractsWithCatalog;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /** @return array{title: string, message: string, cta_label: string, cta_url: string} */
    private function bannerPayload(string $title = 'پیشنهاد ویژه'): array
    {
        return [
            'title' => $title,
            'message' => 'برای سفارش آنلاین از پیوند زیر استفاده کنید.',
            'cta_label' => 'مشاهده پیشنهاد',
            'cta_url' => 'https://example.test/offer',
        ];
    }

    public function test_professional_owner_can_save_and_publish_an_advertising_banner(): void
    {
        [$user, $business, $menu] = $this->createCatalogTenant('professional');
        $menu->update(['status' => MenuStatus::Published]);

        $this->actingAs($user)
            ->put(route('catalog.menu.banner.update'), $this->bannerPayload())
            ->assertRedirect();

        $this->assertSame($this->bannerPayload(), $menu->fresh()->advertising_banner);

        $this->get(route('public.menu.show', [$business->slug, $menu->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('menu.advertising_banner.title', 'پیشنهاد ویژه')
                ->where('menu.advertising_banner.message', 'برای سفارش آنلاین از پیوند زیر استفاده کنید.')
                ->where('menu.advertising_banner.cta_url', 'https://example.test/offer'));
    }

    public function test_economic_owner_cannot_create_an_advertising_banner(): void
    {
        [$user, , $menu] = $this->createCatalogTenant('economic');

        $this->actingAs($user)
            ->put(route('catalog.menu.banner.update'), $this->bannerPayload())
            ->assertForbidden();

        $this->assertNull($menu->fresh()->advertising_banner);
    }

    public function test_existing_banner_can_be_preserved_or_removed_after_downgrade_but_not_changed(): void
    {
        [$user, , $menu] = $this->createCatalogTenant('professional');
        $banner = $this->bannerPayload();

        $this->actingAs($user)
            ->put(route('catalog.menu.banner.update'), $banner)
            ->assertRedirect();

        $subscription = $menu->business->subscriptions()->latest('id')->firstOrFail();
        $snapshot = $subscription->features_snapshot;
        $snapshot['advertising_banner'] = false;
        $subscription->update(['features_snapshot' => $snapshot]);

        $this->actingAs($user)
            ->put(route('catalog.menu.banner.update'), $banner)
            ->assertRedirect();

        $changed = $banner;
        $changed['title'] = 'عنوان جدید';

        $this->actingAs($user)
            ->put(route('catalog.menu.banner.update'), $changed)
            ->assertForbidden();

        $this->assertSame($banner, $menu->fresh()->advertising_banner);

        $this->actingAs($user)
            ->put(route('catalog.menu.banner.update'), [
                'title' => null,
                'message' => null,
                'cta_label' => null,
                'cta_url' => null,
            ])
            ->assertRedirect();

        $this->assertNull($menu->fresh()->advertising_banner);
    }

    public function test_public_menu_hides_existing_banner_after_downgrade_without_stale_cache(): void
    {
        config(['cache.default' => 'array']);
        Cache::store('array')->flush();

        [$user, $business, $menu] = $this->createCatalogTenant('professional');
        $banner = $this->bannerPayload();
        $this->actingAs($user)
            ->put(route('catalog.menu.banner.update'), $banner)
            ->assertRedirect();

        $menu->update(['status' => MenuStatus::Published]);
        $url = route('public.menu.show', [$business->slug, $menu->slug]);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('menu.advertising_banner.title', $banner['title']));

        $subscription = $business->subscriptions()->latest('id')->firstOrFail();
        $snapshot = $subscription->features_snapshot;
        $snapshot['advertising_banner'] = false;
        $subscription->update(['features_snapshot' => $snapshot]);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('menu.advertising_banner', null));

        $this->assertSame($banner, $menu->fresh()->advertising_banner);
    }

    public function test_advertising_banner_rejects_non_https_call_to_action_urls(): void
    {
        [$user] = $this->createCatalogTenant('professional');
        $banner = $this->bannerPayload();
        $banner['cta_url'] = 'http://example.test/offer';

        $this->actingAs($user)
            ->put(route('catalog.menu.banner.update'), $banner)
            ->assertSessionHasErrors('cta_url');
    }
}
