<?php

namespace Tests\Feature\Localization;

use App\Modules\Category\Models\Category;
use App\Modules\Menu\Models\Menu;
use App\Modules\Product\Models\Product;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithCatalog;
use Tests\TestCase;

class TranslationPostgresConcurrencyTest extends TestCase
{
    use DatabaseMigrations;
    use InteractsWithCatalog;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Translation row-lock concurrency requires PostgreSQL.');
        }
    }

    public function test_concurrent_locale_writes_are_serialized_and_preserve_both_locales(): void
    {
        [, , $menu] = $this->createCatalogTenant('top');
        $category = $this->createCatalogCategory($menu, 'Coffee');
        $product = $this->createCatalogProduct($menu, $category, 'Espresso');

        [$english, $arabic] = $this->runContendedPair(
            $menu,
            $category,
            $product,
            [
                'locale' => 'en',
                'menu_title' => 'English menu',
                'category_name' => 'English category',
                'product_name' => 'English espresso',
                'product_description' => 'English description',
            ],
            [
                'locale' => 'ar',
                'menu_title' => 'القائمة العربية',
                'category_name' => 'فئة عربية',
                'product_name' => 'إسبريسو',
                'product_description' => 'وصف عربي',
            ],
        );

        $this->assertSame('success', $english['status']);
        $this->assertSame('success', $arabic['status']);

        $this->assertDatabaseHas('menu_translations', [
            'menu_id' => $menu->getKey(),
            'locale' => 'en',
            'title' => 'English menu',
        ]);
        $this->assertDatabaseHas('menu_translations', [
            'menu_id' => $menu->getKey(),
            'locale' => 'ar',
            'title' => 'القائمة العربية',
        ]);
        $this->assertDatabaseHas('category_translations', [
            'category_id' => $category->getKey(),
            'locale' => 'en',
            'name' => 'English category',
        ]);
        $this->assertDatabaseHas('category_translations', [
            'category_id' => $category->getKey(),
            'locale' => 'ar',
            'name' => 'فئة عربية',
        ]);
        $this->assertDatabaseHas('product_translations', [
            'product_id' => $product->getKey(),
            'locale' => 'en',
            'name' => 'English espresso',
            'description' => 'English description',
        ]);
        $this->assertDatabaseHas('product_translations', [
            'product_id' => $product->getKey(),
            'locale' => 'ar',
            'name' => 'إسبريسو',
            'description' => 'وصف عربي',
        ]);
    }

    public function test_failed_translation_transaction_releases_menu_lock_for_waiting_locale_write(): void
    {
        [, , $menu] = $this->createCatalogTenant('top');
        $category = $this->createCatalogCategory($menu, 'Coffee');
        $product = $this->createCatalogProduct($menu, $category, 'Espresso');

        [$failedEnglish, $savedArabic] = $this->runContendedPair(
            $menu,
            $category,
            $product,
            [
                'locale' => 'en',
                'menu_title' => 'Rolled back English',
                'category_name' => 'Rolled back category',
                'product_name' => 'Rolled back espresso',
                'product_description' => 'Rolled back description',
            ],
            [
                'locale' => 'ar',
                'menu_title' => 'القائمة العربية',
                'category_name' => 'فئة عربية',
                'product_name' => 'إسبريسو',
                'product_description' => 'وصف عربي',
            ],
            firstWorkerFailsAfterLock: true,
        );

        $this->assertSame('error', $failedEnglish['status']);
        $this->assertStringContainsString('Intentional failure after acquiring', $failedEnglish['message']);
        $this->assertSame('success', $savedArabic['status']);

        $this->assertDatabaseMissing('menu_translations', [
            'menu_id' => $menu->getKey(),
            'locale' => 'en',
        ]);
        $this->assertDatabaseHas('menu_translations', [
            'menu_id' => $menu->getKey(),
            'locale' => 'ar',
            'title' => 'القائمة العربية',
        ]);
        $this->assertDatabaseHas('category_translations', [
            'category_id' => $category->getKey(),
            'locale' => 'ar',
            'name' => 'فئة عربية',
        ]);
        $this->assertDatabaseHas('product_translations', [
            'product_id' => $product->getKey(),
            'locale' => 'ar',
            'name' => 'إسبريسو',
        ]);
    }

    /**
     * @param  array{locale: string, menu_title: string, category_name: string, product_name: string, product_description: string}  $first
     * @param  array{locale: string, menu_title: string, category_name: string, product_name: string, product_description: string}  $second
     * @return array{0: array{status: string, message?: string}, 1: array{status: string, message?: string}}
     */
    private function runContendedPair(
        Menu $menu,
        Category $category,
        Product $product,
        array $first,
        array $second,
        bool $firstWorkerFailsAfterLock = false,
    ): array {
        $directory = $this->makeSignalDirectory();
        $gate = $this->lockGate($directory);
        $firstWorker = null;
        $secondWorker = null;

        try {
            $firstWorker = $this->startWorker(
                $menu,
                $category,
                $product,
                $first,
                $directory,
                'first',
                holdAfterLock: true,
                failAfterLock: $firstWorkerFailsAfterLock,
            );

            $this->waitForFile($directory.'/first.locked');

            $secondWorker = $this->startWorker(
                $menu,
                $category,
                $product,
                $second,
                $directory,
                'second',
            );

            $this->waitForDatabaseLockWait('second');
            $this->releaseGate($gate);

            return [
                $this->awaitWorker($firstWorker, $directory, 'first'),
                $this->awaitWorker($secondWorker, $directory, 'second'),
            ];
        } finally {
            $this->releaseGate($gate);
            $this->stopWorker($firstWorker);
            $this->stopWorker($secondWorker);
            $this->removeSignalDirectory($directory);
        }
    }

    /**
     * @param  array{locale: string, menu_title: string, category_name: string, product_name: string, product_description: string}  $payload
     * @return array{process: resource, stderr: string}
     */
    private function startWorker(
        Menu $menu,
        Category $category,
        Product $product,
        array $payload,
        string $directory,
        string $workerId,
        bool $holdAfterLock = false,
        bool $failAfterLock = false,
    ): array {
        $command = [
            PHP_BINARY,
            base_path('scripts/translation-concurrency-worker.stub'),
            (string) $menu->getKey(),
            $payload['locale'],
            $payload['menu_title'],
            $category->public_id,
            $payload['category_name'],
            $product->public_id,
            $payload['product_name'],
            $payload['product_description'],
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
            $this->fail("Unable to start PostgreSQL translation worker [{$workerId}].");
        }

        fclose($pipes[0]);

        return [
            'process' => $process,
            'stderr' => $directory.'/'.$workerId.'.stderr',
        ];
    }

    /**
     * @param  array{process: resource, stderr: string}  $worker
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
                ['translation-worker-'.$workerId],
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
        $directory = sys_get_temp_dir().'/translation-pgsql-'.Str::uuid();
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
            $this->fail('Unable to acquire the PostgreSQL translation concurrency gate.');
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
     * @param  array{process: resource, stderr: string}|null  $worker
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
