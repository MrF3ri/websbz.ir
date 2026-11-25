<?php

namespace Tests\Feature\Business;

use App\Modules\Menu\Enums\MenuStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithCatalog;
use Tests\TestCase;

class BusinessIntroductionTest extends TestCase
{
    use InteractsWithCatalog;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_professional_owner_can_set_business_introduction(): void
    {
        [$user, $business] = $this->createCatalogTenant('professional');

        $this->actingAs($user)
            ->put(route('business.settings.update'), [
                'name' => $business->name,
                'introduction' => 'قهوه تازه و تجربه‌ای آرام در قلب شهر.',
            ])
            ->assertRedirect(route('business.settings.edit'));

        $this->assertDatabaseHas('businesses', [
            'id' => $business->getKey(),
            'introduction' => 'قهوه تازه و تجربه‌ای آرام در قلب شهر.',
        ]);
    }

    public function test_economic_owner_cannot_add_business_introduction(): void
    {
        [$user, $business] = $this->createCatalogTenant('economic');

        $this->actingAs($user)
            ->put(route('business.settings.update'), [
                'name' => $business->name,
                'introduction' => 'متن معرفی خارج از امکانات طرح اقتصادی',
            ])
            ->assertForbidden();

        $this->assertNull($business->fresh()->introduction);
    }

    public function test_existing_introduction_can_be_preserved_or_removed_after_downgrade_but_not_changed(): void
    {
        [$user, $business] = $this->createCatalogTenant('professional');
        $introduction = 'معرفی قبلی کسب‌وکار';

        $this->actingAs($user)->put(route('business.settings.update'), [
            'name' => $business->name,
            'introduction' => $introduction,
        ])->assertRedirect();

        $subscription = $business->subscriptions()->latest('id')->firstOrFail();
        $snapshot = $subscription->features_snapshot;
        $snapshot['business_introduction'] = false;
        $subscription->update(['features_snapshot' => $snapshot]);

        $this->actingAs($user)->put(route('business.settings.update'), [
            'name' => $business->name,
            'introduction' => $introduction,
        ])->assertRedirect();

        $this->actingAs($user)->put(route('business.settings.update'), [
            'name' => $business->name,
            'introduction' => 'متن جدید',
        ])->assertForbidden();

        $this->assertSame($introduction, $business->fresh()->introduction);

        $this->actingAs($user)->put(route('business.settings.update'), [
            'name' => $business->name,
            'introduction' => null,
        ])->assertRedirect();

        $this->assertNull($business->fresh()->introduction);
    }

    public function test_public_menu_hides_existing_introduction_after_downgrade_without_stale_cache(): void
    {
        config(['cache.default' => 'array']);
        Cache::store('array')->flush();

        [$user, $business, $menu] = $this->createCatalogTenant('professional');
        $introduction = 'معرفی عمومی کافه';
        $this->actingAs($user)->put(route('business.settings.update'), [
            'name' => $business->name,
            'introduction' => $introduction,
        ])->assertRedirect();

        $menu->update(['status' => MenuStatus::Published]);
        $url = route('public.menu.show', [$business->slug, $menu->slug]);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('business.introduction', $introduction));

        $subscription = $business->subscriptions()->latest('id')->firstOrFail();
        $snapshot = $subscription->features_snapshot;
        $snapshot['business_introduction'] = false;
        $subscription->update(['features_snapshot' => $snapshot]);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('business.introduction', null));

        $this->assertSame($introduction, $business->fresh()->introduction);
    }
}
