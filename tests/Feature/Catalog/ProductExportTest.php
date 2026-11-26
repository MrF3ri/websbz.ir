<?php

namespace Tests\Feature\Catalog;

use App\Modules\Product\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCatalog;
use Tests\TestCase;

class ProductExportTest extends TestCase
{
    use InteractsWithCatalog;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_top_plan_can_download_utf8_excel_compatible_product_csv(): void
    {
        [$user, $business, $menu] = $this->createCatalogTenant('top');
        $category = $this->createCatalogCategory($menu);
        $product = $this->createCatalogProduct($menu, $category);
        $product->update([
            'name' => '=1+1',
            'description' => '+SUM(1,2)',
            'base_price' => 125000,
            'discount_price' => 99000,
        ]);

        $response = $this->actingAs($user)->get('/catalog/products/export');

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('x-content-type-options', 'nosniff');

        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString("'=1+1", $csv);
        $this->assertStringContainsString("'+SUM(1,2)", $csv);
        $this->assertStringContainsString((string) $product->public_id, $csv);
        $this->assertStringContainsString((string) $category->name, $csv);
        $this->assertStringContainsString('125000', $csv);
        $this->assertStringContainsString('99000', $csv);
        $this->assertStringContainsString((string) $business->slug, $response->headers->get('content-disposition'));
    }

    public function test_product_export_is_scoped_to_the_current_business(): void
    {
        [$userA, , $menuA] = $this->createCatalogTenant('top');
        $categoryA = $this->createCatalogCategory($menuA);
        $productA = $this->createCatalogProduct($menuA, $categoryA);
        $productA->update(['name' => 'Business A product']);

        [, , $menuB] = $this->createCatalogTenant('top');
        $categoryB = $this->createCatalogCategory($menuB);
        $productB = $this->createCatalogProduct($menuB, $categoryB);
        $productB->update(['name' => 'Business B private product']);

        $response = $this->actingAs($userA)->get('/catalog/products/export');
        $csv = $response->streamedContent();

        $response->assertOk();
        $this->assertStringContainsString('Business A product', $csv);
        $this->assertStringNotContainsString('Business B private product', $csv);
    }

    public function test_non_top_plan_cannot_export_products(): void
    {
        [$user, $business, $menu] = $this->createCatalogTenant('professional');
        $category = $this->createCatalogCategory($menu);
        $this->createCatalogProduct($menu, $category);

        $this->actingAs($user)
            ->get('/catalog/products/export')
            ->assertNotFound();
    }

    public function test_empty_catalog_export_still_contains_column_headers(): void
    {
        [$user] = $this->createCatalogTenant('top');

        $response = $this->actingAs($user)->get('/catalog/products/export');

        $response->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('شناسه محصول', $csv);
        $this->assertStringContainsString('نام محصول', $csv);
    }
}
