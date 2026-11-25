<?php

namespace Tests\Feature\PublicMenu;

use App\Modules\Menu\Enums\MenuStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCatalog;
use Tests\TestCase;

class PublicMenuTenantIsolationTest extends TestCase
{
    use InteractsWithCatalog;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_public_menu_route_cannot_resolve_another_business_menu(): void
    {
        [, $businessA] = $this->createCatalogTenant();
        [, $businessB, $menuB] = $this->createCatalogTenant();

        $menuB->update([
            'slug' => 'tenant-b-menu',
            'status' => MenuStatus::Published,
        ]);

        $this->get(route('public.menu.show', [$businessA->slug, $menuB->slug]))
            ->assertNotFound();

        $this->get(route('public.menu.show', [$businessB->slug, $menuB->slug]))
            ->assertOk();
    }
}
