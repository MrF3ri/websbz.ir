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

class ProductImageUploadTest extends TestCase
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

    public function test_professional_can_upload_one_private_product_image_and_public_menu_serves_it(): void
    {
        [$user, $business, $menu] = $this->createCatalogTenant('professional');
        $category = $this->createCatalogCategory($menu);
        $product = $this->createCatalogProduct($menu, $category, 'Espresso', status: ProductStatus::Published);
        $menu->update(['status' => MenuStatus::Published]);

        $this->actingAs($user)
            ->post(route('catalog.products.images.store', $product->public_id), [
                'image' => UploadedFile::fake()->image('espresso.jpg', 120, 80),
            ])
            ->assertRedirect();

        $image = $product->images()->with('media')->firstOrFail();
        $this->assertSame($business->getKey(), $image->business_id);
        $this->assertSame('image/jpeg', $image->media->mime_type);
        $this->assertSame(120, $image->media->width);
        $this->assertSame(80, $image->media->height);
        Storage::disk('media')->assertExists($image->media->path);
        $this->assertFalse(Storage::disk('public')->exists($image->media->path));

        $this->actingAs($user)
            ->post(route('catalog.products.images.store', $product->public_id), [
                'image' => UploadedFile::fake()->image('second.jpg', 120, 80),
            ])
            ->assertForbidden();

        $this->get(route('public.menu.show', [$business->slug, $menu->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('categories.0.products.0.images.0.width', 120)
                ->where('categories.0.products.0.images.0.height', 80));

        $this->get(route('media.public', $image->media->public_id))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_economic_plan_cannot_upload_product_images(): void
    {
        [$user, , $menu] = $this->createCatalogTenant('economic');
        $category = $this->createCatalogCategory($menu);
        $product = $this->createCatalogProduct($menu, $category);

        $this->actingAs($user)
            ->post(route('catalog.products.images.store', $product->public_id), [
                'image' => UploadedFile::fake()->image('photo.jpg', 100, 100),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('media', 0);
        $this->assertDatabaseCount('product_images', 0);
        Storage::disk('media')->assertDirectoryEmpty('businesses');
    }

    public function test_top_plan_can_upload_at_most_five_images_per_product(): void
    {
        [$user, , $menu] = $this->createCatalogTenant('top');
        $category = $this->createCatalogCategory($menu);
        $product = $this->createCatalogProduct($menu, $category);

        for ($index = 1; $index <= 5; $index++) {
            $this->actingAs($user)
                ->post(route('catalog.products.images.store', $product->public_id), [
                    'image' => UploadedFile::fake()->image("photo-{$index}.jpg", 100, 100),
                ])
                ->assertRedirect();
        }

        $this->actingAs($user)
            ->post(route('catalog.products.images.store', $product->public_id), [
                'image' => UploadedFile::fake()->image('photo-6.jpg', 100, 100),
            ])
            ->assertForbidden();

        $this->assertSame(5, $product->images()->count());
        $this->assertDatabaseCount('media', 5);
    }

    public function test_product_image_upload_cannot_cross_business_boundaries(): void
    {
        [$userA] = $this->createCatalogTenant('professional');
        [, , $menuB] = $this->createCatalogTenant('professional');
        $categoryB = $this->createCatalogCategory($menuB);
        $productB = $this->createCatalogProduct($menuB, $categoryB);

        $this->actingAs($userA)
            ->post(route('catalog.products.images.store', $productB->public_id), [
                'image' => UploadedFile::fake()->image('cross-tenant.jpg', 100, 100),
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('media', 0);
        $this->assertDatabaseCount('product_images', 0);
    }

    public function test_public_image_is_hidden_when_product_or_menu_is_not_published(): void
    {
        [$user, , $menu] = $this->createCatalogTenant('professional');
        $category = $this->createCatalogCategory($menu);
        $product = $this->createCatalogProduct($menu, $category);

        $this->actingAs($user)
            ->post(route('catalog.products.images.store', $product->public_id), [
                'image' => UploadedFile::fake()->image('draft.jpg', 100, 100),
            ])
            ->assertRedirect();

        $image = $product->images()->with('media')->firstOrFail();

        $this->get(route('media.public', $image->media->public_id))
            ->assertNotFound();
    }

    public function test_mismatched_image_extension_is_rejected_without_persisting_file(): void
    {
        [$user, , $menu] = $this->createCatalogTenant('professional');
        $category = $this->createCatalogCategory($menu);
        $product = $this->createCatalogProduct($menu, $category);
        $upload = UploadedFile::fake()->createWithContent('not-an-image.jpg', 'plain text content');

        $this->actingAs($user)
            ->post(route('catalog.products.images.store', $product->public_id), [
                'image' => $upload,
            ])
            ->assertSessionHasErrors('image');

        $this->assertDatabaseCount('media', 0);
        $this->assertDatabaseCount('product_images', 0);
        Storage::disk('media')->assertDirectoryEmpty('businesses');
    }

    public function test_owner_can_delete_an_image_and_remove_its_private_file(): void
    {
        [$user, , $menu] = $this->createCatalogTenant('professional');
        $category = $this->createCatalogCategory($menu);
        $product = $this->createCatalogProduct($menu, $category);

        $this->actingAs($user)
            ->post(route('catalog.products.images.store', $product->public_id), [
                'image' => UploadedFile::fake()->image('remove.jpg', 100, 100),
            ])
            ->assertRedirect();

        $image = $product->images()->with('media')->firstOrFail();
        $path = $image->media->path;
        $mediaPublicId = $image->media->public_id;

        $this->actingAs($user)
            ->delete(route('catalog.products.images.destroy', [$product->public_id, $mediaPublicId]))
            ->assertRedirect();

        $this->assertDatabaseCount('product_images', 0);
        $this->assertDatabaseCount('media', 0);
        Storage::disk('media')->assertMissing($path);
    }
    public function test_owner_can_reorder_all_images_for_a_product(): void
    {
        [$user, , $menu] = $this->createCatalogTenant('top');
        $category = $this->createCatalogCategory($menu);
        $product = $this->createCatalogProduct($menu, $category);

        foreach (['first.jpg', 'second.jpg'] as $name) {
            $this->actingAs($user)
                ->post(route('catalog.products.images.store', $product->public_id), [
                    'image' => UploadedFile::fake()->image($name, 100, 100),
                ])
                ->assertRedirect();
        }

        $images = $product->images()->with('media')->orderBy('sort_order')->get();
        $ids = $images->pluck('media.public_id')->all();

        $this->actingAs($user)
            ->put(route('catalog.products.images.reorder', $product->public_id), [
                'media_public_ids' => array_reverse($ids),
            ])
            ->assertRedirect();

        $this->assertSame(
            array_reverse($ids),
            $product->images()->with('media')->orderBy('sort_order')->get()->pluck('media.public_id')->all(),
        );
    }

}
