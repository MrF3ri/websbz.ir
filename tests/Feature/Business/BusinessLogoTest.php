<?php

namespace Tests\Feature\Business;

use App\Enums\BusinessRole;
use App\Modules\Menu\Enums\MenuStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithCatalog;
use Tests\TestCase;

class BusinessLogoTest extends TestCase
{
    use InteractsWithCatalog;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('media');
        Storage::fake('public');
        config(['filesystems.media_disk' => 'media']);
    }

    public function test_professional_owner_can_upload_private_logo_and_public_menu_serves_it(): void
    {
        [$user, $business, $menu] = $this->createCatalogTenant('professional');
        $menu->update(['status' => MenuStatus::Published]);

        $this->actingAs($user)
            ->post(route('business.logo.store'), [
                'logo' => UploadedFile::fake()->image('brand.png', 256, 256),
            ])
            ->assertRedirect();

        $business = $business->fresh();
        $media = $business->logo()->firstOrFail();

        $this->assertSame($business->getKey(), $media->business_id);
        $this->assertSame('image/png', $media->mime_type);
        $this->assertSame(256, $media->width);
        Storage::disk('media')->assertExists($media->path);
        Storage::disk('public')->assertMissing($media->path);

        $url = route('media.business-logo', [$business->public_id, $media->public_id]);

        $this->get(route('public.menu.show', [$business->slug, $menu->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('business.logo_url', $url));

        $this->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_economic_plan_cannot_upload_business_logo(): void
    {
        [$user, $business] = $this->createCatalogTenant('economic');

        $this->actingAs($user)
            ->post(route('business.logo.store'), [
                'logo' => UploadedFile::fake()->image('brand.png', 128, 128),
            ])
            ->assertForbidden();

        $this->assertNull($business->fresh()->logo_media_id);
        $this->assertDatabaseCount('media', 0);
        Storage::disk('media')->assertDirectoryEmpty('businesses');
    }

    public function test_manager_cannot_upload_business_logo(): void
    {
        [$user, $business] = $this->createCatalogTenant('professional', BusinessRole::Manager);

        $this->actingAs($user)
            ->post(route('business.logo.store'), [
                'logo' => UploadedFile::fake()->image('brand.png', 128, 128),
            ])
            ->assertForbidden();

        $this->assertNull($business->fresh()->logo_media_id);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_replacing_logo_removes_previous_private_object_and_metadata(): void
    {
        [$user, $business] = $this->createCatalogTenant('professional');

        $this->actingAs($user)->post(route('business.logo.store'), [
            'logo' => UploadedFile::fake()->image('first.png', 128, 128),
        ])->assertRedirect();

        $oldMedia = $business->fresh()->logo()->firstOrFail();
        $oldPath = $oldMedia->path;

        $this->actingAs($user)->post(route('business.logo.store'), [
            'logo' => UploadedFile::fake()->image('second.png', 256, 256),
        ])->assertRedirect();

        $newMedia = $business->fresh()->logo()->firstOrFail();

        $this->assertNotSame($oldMedia->getKey(), $newMedia->getKey());
        $this->assertDatabaseMissing('media', ['id' => $oldMedia->getKey()]);
        Storage::disk('media')->assertMissing($oldPath);
        Storage::disk('media')->assertExists($newMedia->path);
    }

    public function test_owner_can_remove_logo_and_private_file(): void
    {
        [$user, $business] = $this->createCatalogTenant('professional');

        $this->actingAs($user)->post(route('business.logo.store'), [
            'logo' => UploadedFile::fake()->image('brand.png', 128, 128),
        ])->assertRedirect();

        $media = $business->fresh()->logo()->firstOrFail();

        $this->actingAs($user)
            ->delete(route('business.logo.destroy'))
            ->assertRedirect();

        $this->assertNull($business->fresh()->logo_media_id);
        $this->assertDatabaseMissing('media', ['id' => $media->getKey()]);
        Storage::disk('media')->assertMissing($media->path);
    }

    public function test_public_logo_is_not_available_without_a_published_menu(): void
    {
        [$user, $business] = $this->createCatalogTenant('professional');

        $this->actingAs($user)->post(route('business.logo.store'), [
            'logo' => UploadedFile::fake()->image('brand.png', 128, 128),
        ])->assertRedirect();

        $media = $business->fresh()->logo()->firstOrFail();

        $this->get(route('media.business-logo', [$business->public_id, $media->public_id]))
            ->assertNotFound();
    }

    public function test_logo_upload_rejects_mismatched_file_content(): void
    {
        [$user, $business] = $this->createCatalogTenant('professional');
        $invalid = UploadedFile::fake()->createWithContent('not-a-logo.png', 'plain text');

        $this->actingAs($user)
            ->post(route('business.logo.store'), ['logo' => $invalid])
            ->assertSessionHasErrors('logo');

        $this->assertNull($business->fresh()->logo_media_id);
        $this->assertDatabaseCount('media', 0);
        Storage::disk('media')->assertDirectoryEmpty('businesses');
    }
}
