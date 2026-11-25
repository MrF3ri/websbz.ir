<?php

namespace Tests\Feature\Media;

use App\Modules\Product\Enums\ProductStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\AwsS3V3\PortableVisibilityConverter;
use Tests\Concerns\InteractsWithCatalog;
use Tests\TestCase;

class S3MediaStorageIntegrationTest extends TestCase
{
    use InteractsWithCatalog;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        if (
            getenv('MEDIA_S3_INTEGRATION') !== '1'
            || getenv('MEDIA_S3_BUCKET_IS_NON_PRODUCTION') !== '1'
            || ! class_exists(PortableVisibilityConverter::class)
            || blank(config('filesystems.disks.s3.key'))
            || blank(config('filesystems.disks.s3.secret'))
            || blank(config('filesystems.disks.s3.bucket'))
            || blank(config('filesystems.disks.s3.region'))
        ) {
            $this->markTestSkipped('A deliberately enabled non-production S3-compatible bucket is required.');
        }

        config(['filesystems.media_disk' => 's3']);
        Storage::forgetDisk('s3');
    }

    public function test_upload_variants_delivery_and_delete_work_against_the_non_production_bucket(): void
    {
        [$user, , $menu] = $this->createCatalogTenant('professional');
        $category = $this->createCatalogCategory($menu);
        $product = $this->createCatalogProduct($menu, $category, 'S3 integration image', status: ProductStatus::Published);
        $menu->update(['status' => 'published']);

        $this->actingAs($user)
            ->post(route('catalog.products.images.store', $product->public_id), [
                'image' => UploadedFile::fake()->image('s3-integration.jpg', 1200, 800),
            ])
            ->assertRedirect();

        $media = $product->images()->with('media')->firstOrFail()->media;
        $disk = Storage::disk('s3');

        try {
            $this->assertTrue($disk->exists($media->path));
            $this->assertTrue($disk->exists($media->variants['thumbnail']['path']));
            $this->assertTrue($disk->exists($media->variants['medium']['path']));

            $this->get(route('media.public', [
                'mediaPublicId' => $media->public_id,
                'variant' => 'thumbnail',
            ]))->assertOk();

            $this->actingAs($user)
                ->delete(route('catalog.products.images.destroy', [$product->public_id, $media->public_id]))
                ->assertRedirect();

            $this->assertFalse($disk->exists($media->path));
            $this->assertFalse($disk->exists($media->variants['thumbnail']['path']));
            $this->assertFalse($disk->exists($media->variants['medium']['path']));
        } finally {
            $disk->delete([
                $media->path,
                $media->variants['thumbnail']['path'],
                $media->variants['medium']['path'],
            ]);
        }
    }
}
