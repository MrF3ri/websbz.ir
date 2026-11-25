<?php

namespace Tests\Feature\Menu;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCatalog;
use Tests\TestCase;

class MenuTenantIntegrationTest extends TestCase
{
    use InteractsWithCatalog;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_foreign_active_business_session_cannot_redirect_theme_updates_to_another_tenant(): void
    {
        [$userA, $businessA, $menuA] = $this->createCatalogTenant('professional');
        [, $businessB, $menuB] = $this->createCatalogTenant('professional');

        $this->actingAs($userA)
            ->withSession(['active_business_id' => $businessB->getKey()])
            ->put(route('catalog.menu.theme.update'), [
                'template' => 'modern',
                'primary_color' => '#365B4A',
                'font' => 'system',
                'mode' => 'light',
            ])
            ->assertRedirect();

        $this->assertSame($businessA->getKey(), $menuA->fresh()->business_id);
        $this->assertSame('modern', $menuA->fresh()->theme['template']);
        $this->assertNull($menuB->fresh()->theme);
        $this->assertSame($businessA->getKey(), session('active_business_id'));
    }

    public function test_foreign_active_business_session_cannot_redirect_banner_updates_to_another_tenant(): void
    {
        [$userA, $businessA, $menuA] = $this->createCatalogTenant('professional');
        [, $businessB, $menuB] = $this->createCatalogTenant('professional');

        $this->actingAs($userA)
            ->withSession(['active_business_id' => $businessB->getKey()])
            ->put(route('catalog.menu.banner.update'), [
                'title' => 'پیشنهاد ویژه',
                'message' => 'فقط برای مشتریان کسب‌وکار اول',
                'cta_label' => null,
                'cta_url' => null,
            ])
            ->assertRedirect();

        $this->assertSame($businessA->getKey(), $menuA->fresh()->business_id);
        $this->assertSame('پیشنهاد ویژه', $menuA->fresh()->advertising_banner['title']);
        $this->assertNull($menuB->fresh()->advertising_banner);
        $this->assertSame($businessA->getKey(), session('active_business_id'));
    }
}
