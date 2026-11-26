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

    public function test_non_mp4_upload_is_rejected_without_storing_a_file(): void
    {
        [$user, , $menu] = $this->createCatalogTenant('top');
        $category = $this->createCatalogCategory($menu);
        $product = $this->createCatalogProduct($menu, $category);
        $file = UploadedFile::fake()->createWithContent('not-a-video.mp4', 'not an mp4');

        $this->actingAs($user)
            ->post(route('catalog.products.video.store', $product->public_id), ['video' => $file])
            ->assertRedirect();

        $this->assertDatabaseCount('product_videos', 0);
        $this->assertDatabaseCount('media', 0);
        Storage::disk('media')->assertDirectoryEmpty('businesses');
    }

    public function test_economic_plan_cannot_upload_product_video(): void
    {
        [$user, , $menu] = $this->createCatalogTenant('economic');
        $category = $this->createCatalogCategory($menu);
        $product = $this->createCatalogProduct($menu, $category);

        $this->actingAs($user)
            ->post(route('catalog.products.video.store', $product->public_id), [
                'video' => UploadedFile::fake()->createWithContent('clip.mp4', 'not a valid mp4'),
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('product_videos', 0);
    }
}
