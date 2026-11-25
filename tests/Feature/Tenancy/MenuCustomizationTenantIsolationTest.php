<?php

namespace Tests\Feature\Tenancy;

use App\Modules\Product\Enums\ProductStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithCatalog;
use Tests\TestCase;

class MenuCustomizationTenantIsolationTest extends TestCase
{
    use InteractsWithCatalog;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('media');
        config(['filesystems.media_disk' => 'media']);
    }

    public function test_foreign_active_business_session_cannot_update_another_tenant_product(): void
    {
        [$userA] = $this->createCatalogTenant('professional');
        [, $businessB, $menuB] = $this->createCatalogTenant('professional');
        $categoryB = $this->createCatalogCategory($menuB);
        $productB = $this->createCatalogProduct(
            $menuB,
            $categoryB,
            'Tenant B product',
            status: ProductStatus::Published,
        );

        $this->actingAs($userA)
            ->withSession(['active_business_id' => $businessB->getKey()])
            ->put(route('catalog.products.update', $productB->public_id), [
                'category_public_id' => $categoryB->public_id,
                'name' => 'Unauthorized change',
                'description' => 'Must remain unchanged',
                'base_price' => 150000,
                'discount_price' => null,
                'is_available' => true,
                'status' => ProductStatus::Published->value,
            ])
            ->assertNotFound();

        $this->assertSame('Tenant B product', $productB->fresh()->name);
    }

    public function test_foreign_active_business_session_scopes_business_settings_to_authenticated_tenant(): void
    {
        [$userA, $businessA] = $this->createCatalogTenant('professional');
        [, $businessB] = $this->createCatalogTenant('professional');

        $this->actingAs($userA)
            ->withSession(['active_business_id' => $businessB->getKey()])
            ->put(route('business.settings.update'), [
                'name' => 'Tenant A updated',
                'introduction' => 'معرفی متعلق به کسب‌وکار اول',
            ])
            ->assertRedirect(route('business.settings.edit'));

        $this->assertSame('Tenant A updated', $businessA->fresh()->name);
        $this->assertSame('معرفی متعلق به کسب‌وکار اول', $businessA->fresh()->introduction);
        $this->assertSame($businessB->name, $businessB->fresh()->name);
        $this->assertNull($businessB->fresh()->introduction);
    }

    public function test_foreign_active_business_session_scopes_logo_upload_to_authenticated_tenant(): void
    {
        [$userA, $businessA] = $this->createCatalogTenant('professional');
        [, $businessB] = $this->createCatalogTenant('professional');

        $this->actingAs($userA)
            ->withSession(['active_business_id' => $businessB->getKey()])
            ->post(route('business.logo.store'), [
                'logo' => UploadedFile::fake()->image('tenant-a.png', 128, 128),
            ])
            ->assertRedirect();

        $this->assertTrue($businessA->fresh()->logo()->exists());
        $this->assertFalse($businessB->fresh()->logo()->exists());
        $this->assertDatabaseHas('media', [
            'business_id' => $businessA->getKey(),
            'mime_type' => 'image/png',
        ]);
        $this->assertDatabaseMissing('media', [
            'business_id' => $businessB->getKey(),
        ]);
    }
}
