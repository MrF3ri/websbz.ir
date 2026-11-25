<?php

namespace Tests\Feature\Media;

use App\Modules\Business\Models\Business;
use App\Modules\Media\Actions\UploadProductImageAction;
use App\Modules\Product\Models\Product;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\InteractsWithCatalog;
use Tests\TestCase;

class ProductImageConcurrencyTest extends TestCase
{
    use DatabaseMigrations;
    use InteractsWithCatalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('media');
        config(['filesystems.media_disk' => 'media']);
    }

    public function test_two_postgresql_processes_cannot_exceed_the_image_quota_boundary(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! function_exists('pcntl_fork')) {
            $this->markTestSkipped('This multi-process quota test requires PostgreSQL and pcntl.');
        }

        [$user, , $menu] = $this->createCatalogTenant('top');
        $category = $this->createCatalogCategory($menu);
        $product = $this->createCatalogProduct($menu, $category);

        for ($index = 1; $index <= 4; $index++) {
            $this->actingAs($user)
                ->post(route('catalog.products.images.store', $product->public_id), [
                    'image' => UploadedFile::fake()->image('existing-'.$index.'.jpg', 120, 80),
                ])
                ->assertRedirect();
        }

        $source = UploadedFile::fake()->image('parallel.jpg', 120, 80);
        $sourcePath = $source->getPathname();
        $businessId = $menu->business_id;
        $productId = $product->getKey();
        $resultPathA = tempnam(sys_get_temp_dir(), 'websbz-upload-a-');
        $resultPathB = tempnam(sys_get_temp_dir(), 'websbz-upload-b-');

        if (! is_string($resultPathA) || ! is_string($resultPathB)) {
            $this->fail('Unable to allocate process result files.');
        }

        $resultPaths = [$resultPathA, $resultPathB];
        $children = [];

        foreach ($resultPaths as $resultPath) {
            $pid = pcntl_fork();

            if (! is_int($pid) || $pid < 0) {
                $this->fail('Unable to fork a PostgreSQL upload worker.');
            }

            if ($pid === 0) {
                DB::purge();

                try {
                    $business = Business::query()->findOrFail($businessId);
                    $freshProduct = Product::query()->findOrFail($productId);
                    $file = new UploadedFile($sourcePath, 'parallel.jpg', 'image/jpeg', null, true);
                    app(UploadProductImageAction::class)->execute($business, $freshProduct, $file);
                    file_put_contents($resultPath, 'uploaded');
                    exit(0);
                } catch (ValidationException) {
                    file_put_contents($resultPath, 'rejected');
                    exit(0);
                } catch (\Throwable $exception) {
                    file_put_contents($resultPath, 'error: '.$exception::class.' '.$exception->getMessage());
                    exit(1);
                }
            }

            $children[] = $pid;
        }

        foreach ($children as $index => $pid) {
            $status = 0;
            $waitedPid = pcntl_waitpid($pid, $status);
            $this->assertSame($pid, $waitedPid);
            $this->assertTrue(pcntl_wifexited($status));
            $this->assertSame(0, pcntl_wexitstatus($status));
        }

        DB::purge();

        $results = array_map(
            static fn (string $path): string => (string) file_get_contents($path),
            $resultPaths,
        );

        foreach ($resultPaths as $path) {
            @unlink($path);
        }

        sort($results);
        $this->assertSame(['rejected', 'uploaded'], $results);
        $this->assertSame(5, $product->images()->count());
    }
}
