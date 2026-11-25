<?php

namespace Tests\Feature\Media;

use App\Modules\Menu\Enums\MenuStatus;
use App\Modules\Product\Enums\ProductStatus;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Concerns\InteractsWithCatalog;
use Tests\TestCase;

class ProductImageStorageFailureTest extends TestCase
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

    public function test_storage_delete_failure_keeps_media_rows_for_a_safe_retry(): void
    {
        [$user, , $menu] = $this->createCatalogTenant('professional');
        $category = $this->createCatalogCategory($menu);
        $product = $this->createCatalogProduct($menu, $category, 'Espresso', status: ProductStatus::Published);
        $menu->update(['status' => MenuStatus::Published]);

        $this->actingAs($user)
            ->post(route('catalog.products.images.store', $product->public_id), [
                'image' => UploadedFile::fake()->image('espresso.jpg', 400, 300),
            ])
            ->assertRedirect();

        $image = $product->images()->with('media')->firstOrFail();
        $media = $image->media;

        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('exists')->once()->with($media->path)->andReturn(true);
        $disk->shouldReceive('delete')->once()->with($media->path)->andReturn(false);
        Storage::shouldReceive('disk')->once()->with('media')->andReturn($disk);

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($user)->delete(route('catalog.products.images.destroy', [
                $product->public_id,
                $media->public_id,
            ]));
            $this->fail('A storage deletion failure must abort the database deletion.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Unable to delete product image from storage.', $exception->getMessage());
        }

        $this->assertDatabaseCount('product_images', 1);
        $this->assertDatabaseCount('media', 1);
        $this->assertDatabaseHas('media', ['id' => $media->getKey(), 'path' => $media->path]);
    }
}
