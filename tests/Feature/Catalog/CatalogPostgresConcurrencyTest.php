<?php

namespace Tests\Feature\Catalog;

use App\Modules\Business\Models\Business;
use App\Modules\Category\Models\Category;
use App\Modules\Menu\Models\Menu;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithCatalog;
use Tests\TestCase;

class CatalogPostgresConcurrencyTest extends TestCase
{
    use DatabaseMigrations;
    use InteractsWithCatalog;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Catalog lock concurrency requires PostgreSQL.');
        }
    }

    public function test_catalog_quota_creation_is_serialized_and_menu_locks_are_released(): void
    {
        [$productUser, $productBusiness, $productMenu] = $this->createCatalogTenant('economic');
        $productCategory = $this->createCatalogCategory($productMenu);

        for ($index = 1; $index <= 19; $index++) {
            $this->createCatalogProduct(
                $productMenu,
                $productCategory,
                "Existing Product {$index}",
                $index,
            );
        }

        [$productFirst, $productSecond] = $this->runContendedPair(
            'product',
            $productBusiness,
            $productMenu,
            $productCategory,
            'Concurrent Product A',
            'Concurrent Product B',
        );

        $this->assertSame('success', $productFirst['status']);
        $this->assertSame('forbidden', $productSecond['status']);
        $this->assertSame(20, $productMenu->products()->count());

        [, $categoryBusiness, $categoryMenu] = $this->createCatalogTenant('economic');
        $this->createCatalogCategory($categoryMenu, 'Existing Category');

        [$categoryFirst, $categorySecond] = $this->runContendedPair(
            'category',
            $categoryBusiness,
            $categoryMenu,
            null,
            'Concurrent Category A',
            'Concurrent Category B',
        );

        $this->assertSame('success', $categoryFirst['status']);
        $this->assertSame('forbidden', $categorySecond['status']);
        $this->assertSame(2, $categoryMenu->categories()->count());

        [, $rollbackBusiness, $rollbackMenu] = $this->createCatalogTenant('economic');
        $rollbackCategory = $this->createCatalogCategory($rollbackMenu);

        for ($index = 1; $index <= 19; $index++) {
            $this->createCatalogProduct(
                $rollbackMenu,
                $rollbackCategory,
                "Rollback Product {$index}",
                $index,
            );
        }

        [$rollbackFirst, $rollbackSecond] = $this->runContendedPair(
            'product',
            $rollbackBusiness,
            $rollbackMenu,
            $rollbackCategory,
            'Rolled Back Product',
            'Product After Rollback',
            firstWorkerFailsAfterLock: true,
        );

        $this->assertSame('error', $rollbackFirst['status']);
        $this->assertStringContainsString('Intentional failure after acquiring', $rollbackFirst['message']);
        $this->assertSame('success', $rollbackSecond['status']);
        $this->assertSame(20, $rollbackMenu->products()->count());

        [, $businessA, $menuA] = $this->createCatalogTenant('economic');
        $categoryA = $this->createCatalogCategory($menuA);
        [, $businessB, $menuB] = $this->createCatalogTenant('economic');
        $categoryB = $this->createCatalogCategory($menuB);

        [$independentA, $independentB] = $this->runIndependentBusinessPair(
            $businessA,
            $menuA,
            $categoryA,
            $businessB,
            $menuB,
            $categoryB,
        );

        $this->assertSame('success', $independentA['status']);
        $this->assertSame('success', $independentB['status']);
        $this->assertSame(1, $menuA->products()->count());
        $this->assertSame(1, $menuB->products()->count());
    }

    /**
     * @return array{0: array{status: string, message?: string}, 1: array{status: string, message?: string}}
     */
    private function runContendedPair(
        string $resource,
        Business $business,
        Menu $menu,
        ?Category $category,
        string $firstName,
        string $secondName,
        bool $firstWorkerFailsAfterLock = false,
    ): array {
        $directory = $this->makeSignalDirectory();
        $gate = $this->lockGate($directory);
        $first = null;
        $second = null;

        try {
            $first = $this->startWorker(
                $resource,
                $business,
                $menu,
                $category,
                $firstName,
                $directory,
                'first',
                holdAfterLock: true,
                failAfterLock: $firstWorkerFailsAfterLock,
            );

            $this->waitForFile($directory.'/first.locked');

            $second = $this->startWorker(
                $resource,
                $business,
                $menu,
                $category,
                $secondName,
                $directory,
                'second',
            );

            $this->waitForDatabaseLockWait('second');
            $this->releaseGate($gate);

            return [
                $this->awaitWorker($first, $directory, 'first'),
                $this->awaitWorker($second, $directory, 'second'),
            ];
        } finally {
            $this->releaseGate($gate);
            $this->stopWorker($first);
            $this->stopWorker($second);
            $this->removeSignalDirectory($directory);
        }
    }

    /**
     * @return array{0: array{status: string, message?: string}, 1: array{status: string, message?: string}}
     */
    private function runIndependentBusinessPair(
        Business $businessA,
        Menu $menuA,
        Category $categoryA,
        Business $businessB,
        Menu $menuB,
        Category $categoryB,
    ): array {
        $directory = $this->makeSignalDirectory();
        $gate = $this->lockGate($directory);
        $first = null;
        $second = null;

        try {
            $first = $this->startWorker(
                'product',
                $businessA,
                $menuA,
                $categoryA,
                'Independent Business A Product',
                $directory,
                'business-a',
                holdAfterLock: true,
            );

            $second = $this->startWorker(
                'product',
                $businessB,
                $menuB,
                $categoryB,
                'Independent Business B Product',
                $directory,
                'business-b',
                holdAfterLock: true,
            );

            $this->waitForFile($directory.'/business-a.locked');
            $this->waitForFile($directory.'/business-b.locked');
            $this->releaseGate($gate);

            return [
                $this->awaitWorker($first, $directory, 'business-a'),
                $this->awaitWorker($second, $directory, 'business-b'),
            ];
        } finally {
            $this->releaseGate($gate);
            $this->stopWorker($first);
            $this->stopWorker($second);
            $this->removeSignalDirectory($directory);
        }
    }

    /**
     * @return array{process: resource, pipes: array<int, resource>, stderr: string}
     */
    private function startWorker(
        string $resource,
        Business $business,
        Menu $menu,
        ?Category $category,
        string $name,
        string $directory,
        string $workerId,
        bool $holdAfterLock = false,
        bool $failAfterLock = false,
    ): array {
        $command = [
            PHP_BINARY,
            base_path('scripts/catalog-concurrency-worker.php'),
            $resource,
            (string) $business->getKey(),
            (string) $menu->getKey(),
            (string) ($category?->getKey() ?? 0),
            $name,
            $directory,
            $workerId,
            $holdAfterLock ? '1' : '0',
            $failAfterLock ? '1' : '0',
        ];

        $database = config('database.connections.pgsql');
        $environment = array_merge(getenv() ?: [], [
            'APP_ENV' => 'testing',
            'APP_KEY' => (string) config('app.key'),
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => (string) $database['host'],
            'DB_PORT' => (string) $database['port'],
            'DB_DATABASE' => (string) $database['database'],
            'DB_USERNAME' => (string) $database['username'],
            'DB_PASSWORD' => (string) $database['password'],
            'DB_URL' => '',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
        ]);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', $directory.'/'.$workerId.'.stdout', 'a'],
            2 => ['file', $directory.'/'.$workerId.'.stderr', 'a'],
        ];

        $pipes = [];
        $process = proc_open($command, $descriptors, $pipes, base_path(), $environment);

        if (! is_resource($process)) {
            $this->fail("Unable to start PostgreSQL concurrency worker [{$workerId}].");
        }

        fclose($pipes[0]);

        return [
            'process' => $process,
            'pipes' => [],
            'stderr' => $directory.'/'.$workerId.'.stderr',
        ];
    }

    /**
     * @param  array{process: resource, pipes: array<int, resource>, stderr: string}  $worker
     * @return array{status: string, message?: string}
     */
    private function awaitWorker(array $worker, string $directory, string $workerId): array
    {
        $resultPath = $directory.'/'.$workerId.'.result';

        for ($attempt = 0; $attempt < 1500; $attempt++) {
            if (is_file($resultPath)) {
                $result = json_decode((string) file_get_contents($resultPath), true, 512, JSON_THROW_ON_ERROR);
                $exitCode = proc_close($worker['process']);

                if (! is_array($result) || ! isset($result['status'])) {
                    $this->fail("Worker [{$workerId}] returned an invalid result.");
                }

                if ($result['status'] === 'error' && $exitCode === 0) {
                    $this->fail("Worker [{$workerId}] returned an error with a successful exit code.");
                }

                return $result;
            }

            $status = proc_get_status($worker['process']);

            if (! $status['running']) {
                $stderr = is_file($worker['stderr'])
                    ? (string) file_get_contents($worker['stderr'])
                    : '';

                proc_close($worker['process']);

                $this->fail("Worker [{$workerId}] exited before writing its result. {$stderr}");
            }

            usleep(10_000);
        }

        $this->fail("Timed out waiting for worker [{$workerId}].");

        return ['status' => 'error'];
    }

    private function waitForDatabaseLockWait(string $workerId): void
    {
        for ($attempt = 0; $attempt < 1500; $attempt++) {
            $result = DB::selectOne(
                <<<'SQL'
                SELECT CASE WHEN EXISTS (
                    SELECT 1
                    FROM pg_stat_activity
                    WHERE application_name = ?
                      AND wait_event_type = 'Lock'
                ) THEN 1 ELSE 0 END AS waiting
                SQL,
                ['catalog-worker-'.$workerId],
            );

            if ((int) $result->waiting === 1) {
                return;
            }

            usleep(10_000);
        }

        $this->fail("Worker [{$workerId}] never entered a PostgreSQL lock wait.");
    }

    private function waitForFile(string $path): void
    {
        for ($attempt = 0; $attempt < 1500; $attempt++) {
            if (is_file($path)) {
                return;
            }

            usleep(10_000);
        }

        $this->fail("Timed out waiting for concurrency signal [{$path}].");
    }

    private function makeSignalDirectory(): string
    {
        $directory = sys_get_temp_dir().'/catalog-pgsql-'.Str::uuid();
        mkdir($directory, 0700, true);
        touch($directory.'/gate');

        return $directory;
    }

    /**
     * @return resource
     */
    private function lockGate(string $directory)
    {
        $gate = fopen($directory.'/gate', 'c+');

        if ($gate === false || ! flock($gate, LOCK_EX | LOCK_NB)) {
            $this->fail('Unable to acquire the PostgreSQL concurrency gate.');
        }

        return $gate;
    }

    /**
     * @param  resource|null  $gate
     */
    private function releaseGate($gate): void
    {
        if (is_resource($gate)) {
            flock($gate, LOCK_UN);
            fclose($gate);
        }
    }

    /**
     * @param  array{process: resource, pipes: array<int, resource>, stderr: string}|null  $worker
     */
    private function stopWorker(?array $worker): void
    {
        if ($worker === null || ! is_resource($worker['process'])) {
            return;
        }

        $status = proc_get_status($worker['process']);

        if ($status['running']) {
            proc_terminate($worker['process']);
        }

        proc_close($worker['process']);
    }

    private function removeSignalDirectory(string $directory): void
    {
        foreach (glob($directory.'/*') ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
}
