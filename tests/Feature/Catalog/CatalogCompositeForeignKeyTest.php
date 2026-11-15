<?php

namespace Tests\Feature\Catalog;

use App\Modules\Product\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithCatalog;
use Tests\TestCase;

class CatalogCompositeForeignKeyTest extends TestCase
{
    use InteractsWithCatalog;
    use RefreshDatabase;

    public function test_postgresql_rejects_product_whose_category_belongs_to_another_menu(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Composite catalog FK integration is asserted on PostgreSQL only.');
        }

        [, , $menuA] = $this->createCatalogTenant('top');
        [, , $menuB] = $this->createCatalogTenant('top');
        $categoryB = $this->createCatalogCategory($menuB, 'Foreign Category');

        $this->expectException(QueryException::class);

        Product::query()->create([
            'menu_id' => $menuA->getKey(),
            'category_id' => $categoryB->getKey(),
            'name' => 'Invalid Cross-Menu Product',
            'base_price' => 1000,
            'status' => 'draft',
        ]);
    }
}
