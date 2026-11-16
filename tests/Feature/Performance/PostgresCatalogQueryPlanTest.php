<?php

namespace Tests\Feature\Performance;

use App\Modules\Business\Models\Business;
use App\Modules\Menu\Enums\MenuStatus;
use App\Modules\Menu\Models\Menu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PostgresCatalogQueryPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_and_public_menu_query_plans_on_multi_tenant_dataset(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL query plans require the PostgreSQL test connection.');
        }

        $businesses = Business::factory()->count(20)->create();
        $menus = [];
        $categoryIdsByMenu = [];

        foreach ($businesses as $business) {
            $menu = Menu::query()->create([
                'business_id' => $business->getKey(),
                'title' => 'Performance menu',
                'slug' => 'performance-menu',
                'status' => MenuStatus::Published,
                'default_locale' => 'fa',
            ]);

            $menus[] = $menu;
            $categoryRows = [];

            for ($category = 0; $category < 20; $category++) {
                $categoryRows[] = [
                    'public_id' => (string) Str::ulid(),
                    'menu_id' => $menu->getKey(),
                    'name' => 'Category '.$category,
                    'sort_order' => $category,
                    'is_active' => $category % 12 !== 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::table('categories')->insert($categoryRows);

            $categoryIdsByMenu[$menu->getKey()] = DB::table('categories')
                ->where('menu_id', $menu->getKey())
                ->orderBy('id')
                ->pluck('id')
                ->all();
        }

        $productRows = [];

        foreach ($categoryIdsByMenu as $menuId => $categoryIds) {
            foreach ($categoryIds as $categoryIndex => $categoryId) {
                for ($product = 0; $product < 150; $product++) {
                    $sequence = ($categoryIndex * 150) + $product;

                    $productRows[] = [
                        'public_id' => (string) Str::ulid(),
                        'menu_id' => $menuId,
                        'category_id' => $categoryId,
                        'name' => 'Product '.$sequence,
                        'description' => null,
                        'base_price' => 100000 + $sequence,
                        'discount_price' => null,
                        'is_available' => $sequence % 10 !== 0,
                        'status' => $sequence % 5 === 0 ? 'draft' : 'published',
                        'sort_order' => $product,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if (count($productRows) === 1000) {
                        DB::table('products')->insert($productRows);
                        $productRows = [];
                    }
                }
            }
        }

        if ($productRows !== []) {
            DB::table('products')->insert($productRows);
        }

        $menuId = $menus[0]->getKey();
        $categoryIds = $categoryIdsByMenu[$menuId];

        $publicCategories = DB::table('categories')
            ->where('categories.menu_id', $menuId)
            ->where('categories.is_active', true)
            ->whereExists(function ($query) use ($menuId): void {
                $query->selectRaw('1')
                    ->from('products')
                    ->whereColumn('products.category_id', 'categories.id')
                    ->where('products.menu_id', $menuId)
                    ->where('products.status', 'published')
                    ->where('products.is_available', true);
            })
            ->orderBy('categories.sort_order')
            ->orderBy('categories.id');

        $publicProductsWithoutMenuScope = DB::table('products')
            ->whereIn('category_id', $categoryIds)
            ->where('status', 'published')
            ->where('is_available', true)
            ->orderBy('sort_order')
            ->orderBy('id');

        $publicProductsWithMenuScope = DB::table('products')
            ->where('menu_id', $menuId)
            ->whereIn('category_id', $categoryIds)
            ->where('status', 'published')
            ->where('is_available', true)
            ->orderBy('sort_order')
            ->orderBy('id');

        $catalogProducts = DB::table('products')
            ->where('menu_id', $menuId)
            ->orderBy(
                DB::table('categories')
                    ->select('sort_order')
                    ->whereColumn('categories.id', 'products.category_id')
                    ->limit(1),
            )
            ->orderBy('products.sort_order')
            ->orderBy('products.id');

        $catalogCountsWithoutMenuScope = DB::table('categories')
            ->where('categories.menu_id', $menuId)
            ->select('categories.id')
            ->selectSub(
                DB::table('products')
                    ->selectRaw('count(*)')
                    ->whereColumn('products.category_id', 'categories.id'),
                'products_count',
            );

        $catalogCountsWithMenuScope = DB::table('categories')
            ->where('categories.menu_id', $menuId)
            ->select('categories.id')
            ->selectSub(
                DB::table('products')
                    ->selectRaw('count(*)')
                    ->whereColumn('products.category_id', 'categories.id')
                    ->where('products.menu_id', $menuId),
                'products_count',
            );

        $plans = [
            'public_categories_exists' => $this->explain($publicCategories),
            'public_products_without_menu_scope' => $this->explain($publicProductsWithoutMenuScope),
            'public_products_with_menu_scope' => $this->explain($publicProductsWithMenuScope),
            'catalog_products' => $this->explain($catalogProducts),
            'catalog_counts_without_menu_scope' => $this->explain($catalogCountsWithoutMenuScope),
            'catalog_counts_with_menu_scope' => $this->explain($catalogCountsWithMenuScope),
        ];

        fwrite(STDOUT, PHP_EOL.'POSTGRES_CATALOG_QUERY_PLANS='.json_encode([
            'dataset' => [
                'businesses' => count($businesses),
                'menus' => count($menus),
                'categories' => count($menus) * 20,
                'products' => count($menus) * 20 * 150,
                'target_menu_products' => 20 * 150,
            ],
            'plans' => $plans,
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL);

        foreach ($plans as $plan) {
            $this->assertIsString($plan['node_type']);
            $this->assertNotSame('', $plan['node_type']);
            $this->assertGreaterThanOrEqual(0, $plan['execution_time_ms']);
        }
    }

    /**
     * @return array{
     *     node_type: string,
     *     execution_time_ms: float,
     *     planning_time_ms: float,
     *     actual_rows: int,
     *     shared_hit_blocks: int,
     *     shared_read_blocks: int,
     *     indexes: list<string>,
     *     node_types: list<string>
     * }
     */
    private function explain(\Illuminate\Database\Query\Builder $query): array
    {
        $row = DB::select(
            'EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) '.$query->toSql(),
            $query->getBindings(),
        )[0];

        $document = json_decode($row->{'QUERY PLAN'}, true, flags: JSON_THROW_ON_ERROR);
        $plan = $document[0]['Plan'];
        $nodes = [];
        $indexes = [];
        $this->collectPlanNodes($plan, $nodes, $indexes);

        return [
            'node_type' => $plan['Node Type'],
            'execution_time_ms' => (float) $document[0]['Execution Time'],
            'planning_time_ms' => (float) $document[0]['Planning Time'],
            'actual_rows' => (int) ($plan['Actual Rows'] ?? 0),
            'shared_hit_blocks' => (int) ($plan['Shared Hit Blocks'] ?? 0),
            'shared_read_blocks' => (int) ($plan['Shared Read Blocks'] ?? 0),
            'indexes' => array_values(array_unique($indexes)),
            'node_types' => array_values(array_unique($nodes)),
        ];
    }

    /**
     * @param array<string, mixed> $plan
     * @param list<string> $nodes
     * @param list<string> $indexes
     */
    private function collectPlanNodes(array $plan, array &$nodes, array &$indexes): void
    {
        $nodes[] = (string) $plan['Node Type'];

        if (isset($plan['Index Name'])) {
            $indexes[] = (string) $plan['Index Name'];
        }

        foreach ($plan['Plans'] ?? [] as $child) {
            $this->collectPlanNodes($child, $nodes, $indexes);
        }
    }
}
