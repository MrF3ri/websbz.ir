<?php

namespace Tests\Feature\Media;

use App\Modules\Menu\Enums\MenuStatus;
use App\Modules\Product\Enums\ProductStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithCatalog;
use Tests\TestCase;

class ResponsiveProductImageTest extends TestCase
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

    public function test_upload_generates_thumbnail_and_medium_variants_and_public_srcset(): void
    {
        [$user, $business, $menu] = $this->createCatalogTenant('professional');
        $category = $this->createCatalogCategory($menu);
        $product = $this->createCatalogProduct($menu, $category, 'Espresso', status: ProductStatus::Published);
        $menu->update(['status' => MenuStatus::Published]);

        $this->actingAs($user)
            ->post(route('catalog.products.images.store', $product->public_id), [
                'image' => UploadedFile::fake()->image('espresso.jpg', 1200, 800),
            ])
            ->assertRedirect();

        $media = $product->images()->with('media')->firstOrFail()->media;

        $this->assertSame(320, $media->variants['thumbnail']['width']);
        $this->assertSame(213, $media->variants['thumbnail']['height']);
        $this->assertSame(960, $media->variants['medium']['width']);
        $this->assertSame(640, $media->variants['medium']['height']);
        Storage::disk('media')->assertExists($media->path);
        Storage::disk('media')->assertExists($media->variants['thumbnail']['path']);
        Storage::disk('media')->assertExists($media->variants['medium']['path']);

        $this->get(route('public.menu.show', [$business->slug, $menu->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('categories.0.products.0.images.0.width', 1200)
                ->where('categories.0.products.0.images.0.srcset', fn ($srcset): bool => is_string($srcset) && str_contains($srcset, 'variant=thumbnail') && str_contains($srcset, 'variant=medium')));

        $this->get(route('media.public', ['mediaPublicId' => $media->public_id, 'variant' => 'thumbnail']))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_public_menu_includes_every_published_product_image_in_gallery_order(): void
    {
        [$user, $business, $menu] = $this->createCatalogTenant('top');
        $category = $this->createCatalogCategory($menu);
        $product = $this->createCatalogProduct($menu, $category, 'Latte', status: ProductStatus::Published);
        $menu->update(['status' => MenuStatus::Published]);

        foreach (['first.jpg', 'second.jpg', 'third.jpg'] as $filename) {
            $this->actingAs($user)
                ->post(route('catalog.products.images.store', $product->public_id), [
                    'image' => UploadedFile::fake()->image($filename, 400, 300),
                ])
                ->assertRedirect();
        }

        $expectedIds = $product->images()->with('media')->get()->pluck('media.public_id')->all();

        $this->get(route('public.menu.show', [$business->slug, $menu->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('categories.0.products.0.images', 3)
                ->where('categories.0.products.0.images.0.url', route('media.public', $expectedIds[0]))
                ->where('categories.0.products.0.images.1.url', route('media.public', $expectedIds[1]))
                ->where('categories.0.products.0.images.2.url', route('media.public', $expectedIds[2])));
    }
}
