<?php

namespace Tests\Feature\Localization;

use App\Modules\Localization\Services\LocaleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCatalog;
use Tests\TestCase;

class LocaleRegistryTest extends TestCase
{
    use InteractsWithCatalog;
    use RefreshDatabase;

    public function test_only_top_can_use_all_three_public_menu_locales(): void
    {
        [, $economic] = $this->createCatalogTenant('economic');
        [, $professional] = $this->createCatalogTenant('professional');
        [, $top] = $this->createCatalogTenant('top');

        $registry = app(LocaleRegistry::class);

        $this->assertSame(['fa'], array_keys($registry->availableFor($economic)));
        $this->assertSame(['fa'], array_keys($registry->availableFor($professional)));
        $this->assertSame(['fa', 'en', 'ar'], array_keys($registry->availableFor($top)));

        $this->assertFalse($registry->allows($economic, 'en'));
        $this->assertFalse($registry->allows($professional, 'ar'));
        $this->assertTrue($registry->allows($top, 'en'));
        $this->assertTrue($registry->allows($top, 'ar'));
    }

    public function test_locale_registry_exposes_default_and_direction_metadata(): void
    {
        $registry = app(LocaleRegistry::class);

        $this->assertSame('fa', $registry->default());
        $this->assertSame('rtl', $registry->direction('fa'));
        $this->assertSame('ltr', $registry->direction('en'));
        $this->assertSame('rtl', $registry->direction('ar'));
        $this->assertSame('rtl', $registry->direction('unknown'));
    }
}
