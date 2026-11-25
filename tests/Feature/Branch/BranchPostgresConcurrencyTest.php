<?php

namespace Tests\Feature\Branch;

use App\Modules\Branch\Actions\CreateBranchAction;
use App\Modules\Branch\Actions\EnsurePrimaryBranchAction;
use App\Modules\Branch\Models\Branch;
use App\Modules\Business\Models\Business;
use App\Modules\Category\Models\Category;
use App\Modules\Menu\Models\Menu;
use App\Modules\Product\Models\Product;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithCatalog;
use Tests\TestCase;

class BranchPostgresConcurrencyTest extends TestCase
{
    use DatabaseMigrations;
    use InteractsWithCatalog;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Branch row-lock concurrency requires PostgreSQL.');
        }
    }

    public function test_concurrent_branch_creation_cannot_exceed_the_plan_quota(): void
    {
        [, $business, $menu] = $this->createCatalogTenant('top');
        app(EnsurePrimaryBranchAction::class)->execute($business);

        [$first, $second] = $this->runContendedPair(
            'create-branch',
            $business,
            $menu,
            null,
            'Concurrent Branch A',
            'concurrent-branch-a',
            'first',
            'second',
        );

        $this->assertSame('success', $first['status']);
        $this->assertSame('forbidden', $second['status']);
        $this->assertSame(2, $business->branches()->count());
    }

    public function test_concurrent_branch_settings_writes_are_serialized_without_lost_updates(): void
    {
        [, $business, $menu] = $this->createCatalogTenant('top');
        $category = $this->createCatalogCategory($menu, 'Coffee');
        $productA = $this->createCatalogProduct($menu, $category, 'Espresso A');
        $productB = $this->createCatalogProduct($menu, $category, 'Espresso B');
        app(EnsurePrimaryBranchAction::class)->execute($business);
        $branch = app(CreateBranchAction::class)->execute($business, [
            'name' => 'North Branch',
            'slug' => 'north',
        ]);

        [$first, $second] = $this->runContendedPair(
            'update-settings',
            $business,
            $menu,
            $branch,
            'North espresso A',
            'North espresso B',
            'first',
            'second',
            $productA,
            $productB,
        );

        $this->assertSame('success', $first['status']);
        $this->assertSame('success', $second['status']);
        $this->assertDatabaseHas('branch_product_settings', [
            'branch_id' => $branch->getKey(),
            'product_id' => $productA->getKey(),
            'name_override' => 'North espresso A',
        ]);
        $this->assertDatabaseHas('branch_product_settings', [
            'branch_id' => $branch->getKey(),
            'product_id' => $productB->getKey(),
            'name_override' => 'North espresso B',
        ]);
        $this->assertSame('Espresso A', $productA->fresh()->name);
        $this->assertSame('Espresso B', $productB->fresh()->name);
    }

    public function test_failed_branch_creation_releases_business_lock_for_waiting_worker(): void
    {
        [, $business, $menu] = $this->createCatalogTenant('top');
        app(EnsurePrimaryBranchAction::class)->execute($business);

        [$first, $second] = $this->runContendedPair(
            'create-branch',
            $business,
            $menu,
            null,
            'Rolled Back Branch',
            'rolled-back-branch',
            'first',
            'second',
            firstWorkerFailsAfterLock: true,
        );

        $this->assertSame('error', $first['status']);
        $this->assertStringContainsString('Intentional failure after acquiring', $first['message']);
        $this->assertSame('success', $second['status']);
        $this->assertSame(2, $business->branches()->count());
        $this->assertDatabaseMissing('branches', [
            'business_id' => $business->getKey(),
            'slug' => 'rolled-back-branch',
        ]);
    }

    /**
     * @param  'create-branch'|'update-settings'  $operation
     * @param  array{0: array{status: string, message?: string}, 1: array{status: string, message?: string}}  $unused
     * @return array{0: array{status: string, message?: string}, 1: array{status: string, message?: string}}
     */
    private function runContendedPair(
        string $operation,
        Business $business,
        Menu $menu,
        ?Branch $branch,
        string $firstName,
        string $secondName,
        string $firstSlug,
        string $secondSlug,
        ?Product $firstProduct = null,
        ?Product $secondProduct = null,
        bool $firstWorkerFailsAfterLock = false,
    ): array {
        $directory = $this->makeSignalDirectory();
        $gate = $this->lockGate($directory);
        $first = null;
        $second = null;

        try {
            $first = $this->startWorker(
                $operation,
                $business,
                $menu,
                $branch,
                $firstProduct,
                $firstName,
                $firstSlug,
                $directory,
                'first',
                holdAfterLock: true,
                failAfterLock: $firstWorkerFailsAfterLock,
            );

            $this->waitForFile($directory.'/first.locked');

            $second = $this->startWorker(
                $operation,
                $business,
                $menu,
                $branch,
                $secondProduct,
                $secondName,
                $secondSlug,
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
     * @return array{process: resource, pipes: array<int, resource>, stderr: string}
     */
    private function startWorker(
        string $operation,
        Business $business,
        Menu $menu,
        ?Branch $branch,
        ?Product $product,
        string $name,
        string $slug,
        string $directory,
        string $workerId,
        bool $holdAfterLock = false,
        bool $failAfterLock = false,
    ): array {
        $command = [
            PHP_BINARY,
            base_path('scripts/branch-concurrency-worker.stub'),
            $operation,
            (string) $business->getKey(),
            (string) ($branch?->getKey() ?? 0),
            (string) ($product?->getKey() ?? 0),
            $name,
            $slug,
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
            $this->fail("Unable to start PostgreSQL branch worker [{$workerId}].");
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
                ['branch-worker-'.$workerId],
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
        $directory = sys_get_temp_dir().'/branch-pgsql-'.Str::uuid();
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
            $this->fail('Unable to acquire the PostgreSQL branch concurrency gate.');
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
