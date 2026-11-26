<?php

namespace Tests\Feature\Media;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithCatalog;
use Tests\TestCase;

class ProductVideoUploadTest extends TestCase
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

    public function test_top_plan_can_upload_a_valid_mp4_product_video(): void
    {
        [$user, , $menu] = $this->createCatalogTenant('top');
        $category = $this->createCatalogCategory($menu);
        $product = $this->createCatalogProduct($menu, $category);

        $this->actingAs($user)
            ->post(route('catalog.products.video.store', $product->public_id), [
                'video' => $this->validMp4(),
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('product_videos', 1);
        $this->assertDatabaseHas('media', [
            'mime_type' => 'video/mp4',
            'size' => 140,
            'duration_ms' => 4750,
        ]);

        $media = \App\Modules\Media\Models\Media::query()->where('mime_type', 'video/mp4')->firstOrFail();
        Storage::disk('media')->assertExists($media->path);
    }

    public function test_economic_plan_cannot_upload_a_valid_product_video(): void
    {
        [$user, , $menu] = $this->createCatalogTenant('economic');
        $category = $this->createCatalogCategory($menu);
        $product = $this->createCatalogProduct($menu, $category);

        $this->actingAs($user)
            ->post(route('catalog.products.video.store', $product->public_id), [
                'video' => $this->validMp4(),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('product_videos', 0);
        $this->assertDatabaseCount('media', 0);
        Storage::disk('media')->assertDirectoryEmpty('businesses');
    }

    public function test_video_longer_than_five_seconds_is_rejected(): void
    {
        [$user, , $menu] = $this->createCatalogTenant('top');
        $category = $this->createCatalogCategory($menu);
        $product = $this->createCatalogProduct($menu, $category);

        $this->actingAs($user)
            ->post(route('catalog.products.video.store', $product->public_id), [
                'video' => $this->validMp4(6000),
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('product_videos', 0);
        $this->assertDatabaseCount('media', 0);
        Storage::disk('media')->assertDirectoryEmpty('businesses');
    }

    public function test_non_mp4_upload_is_rejected_without_storing_a_file(): void
    {
        [$user, , $menu] = $this->createCatalogTenant('top');
        $category = $this->createCatalogCategory($menu);
        $product = $this->createCatalogProduct($menu, $category);

        $this->actingAs($user)
            ->post(route('catalog.products.video.store', $product->public_id), [
                'video' => UploadedFile::fake()->createWithContent('not-a-video.mp4', 'not an mp4'),
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('product_videos', 0);
        $this->assertDatabaseCount('media', 0);
        Storage::disk('media')->assertDirectoryEmpty('businesses');
    }

    private function validMp4(int $durationMs = 4750): UploadedFile
    {
        $mvhdPayload = pack('N', 0)
            .pack('N', 0)
            .pack('N', 0)
            .pack('N', 1000)
            .pack('N', $durationMs)
            .str_repeat("\0", 80);
        $mvhd = pack('N', strlen($mvhdPayload) + 8).'mvhd'.$mvhdPayload;
        $moov = pack('N', strlen($mvhd) + 8).'moov'.$mvhd;
        $ftypPayload = 'isom'.pack('N', 0x200).'isomiso2';
        $ftyp = pack('N', strlen($ftypPayload) + 8).'ftyp'.$ftypPayload;

        return UploadedFile::fake()->createWithContent('clip.mp4', $ftyp.$moov);
    }
}
