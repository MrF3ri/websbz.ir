<?php

namespace App\Modules\Media\Http\Controllers;

use App\Modules\Business\Models\Business;
use App\Modules\Media\Actions\UploadProductVideoAction;
use App\Modules\Media\Http\Requests\UploadProductVideoRequest;
use App\Modules\Media\Models\Media;
use App\Modules\Media\Models\ProductVideo;
use App\Modules\Menu\Actions\EnsurePrimaryMenuAction;
use App\Modules\Menu\Enums\MenuStatus;
use App\Modules\Product\Enums\ProductStatus;
use App\Modules\Product\Models\Product;
use App\Modules\PublicMenu\Services\PublicMenuCache;
use App\Modules\Tenancy\Services\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductVideoController
{
    public function store(
        UploadProductVideoRequest $request,
        string $productPublicId,
        CurrentBusiness $currentBusiness,
        EnsurePrimaryMenuAction $ensurePrimaryMenu,
        UploadProductVideoAction $upload,
        PublicMenuCache $publicMenuCache,
    ): RedirectResponse {
        $business = $this->business($currentBusiness);
        $menu = $ensurePrimaryMenu->execute($business);
        $product = $menu->products()->where('public_id', $productPublicId)->firstOrFail();

        Gate::authorize('update', $product);

        $file = $request->file('video');
        abort_unless($file instanceof UploadedFile, 422);
        $upload->execute($business, $product, $file);
        $publicMenuCache->forget($menu);

        return back()->with('success', 'ویدئوی محصول با موفقیت بارگذاری شد.');
    }

    public function destroy(
        string $productPublicId,
        string $mediaPublicId,
        CurrentBusiness $currentBusiness,
        EnsurePrimaryMenuAction $ensurePrimaryMenu,
        PublicMenuCache $publicMenuCache,
    ): RedirectResponse {
        $business = $this->business($currentBusiness);
        $menu = $ensurePrimaryMenu->execute($business);
        $product = $menu->products()->where('public_id', $productPublicId)->firstOrFail();

        Gate::authorize('update', $product);

        $video = $product->videos()
            ->whereHas('media', fn ($query) => $query->where('public_id', $mediaPublicId))
            ->with('media')
            ->firstOrFail();

        $media = $video->media;
        DB::transaction(function () use ($video): void {
            $lockedVideo = ProductVideo::query()
                ->whereKey($video->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $media = Media::query()
                ->whereKey($lockedVideo->media_id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedVideo->delete();
            $media->delete();
        });

        $storage = Storage::disk($media->disk);
        if ($storage->exists($media->path) && ! $storage->delete($media->path)) {
            report(new \RuntimeException('Unable to delete product video from storage.'));
        }

        $publicMenuCache->forget($menu);

        return back()->with('success', 'ویدئوی محصول حذف شد.');
    }

    public function publicVideo(string $mediaPublicId): StreamedResponse
    {
        $video = ProductVideo::query()
            ->with(['media', 'product.menu.business'])
            ->whereHas('media', fn ($query) => $query->where('public_id', $mediaPublicId))
            ->whereHas('product', function ($query): void {
                $query
                    ->where('status', ProductStatus::Published)
                    ->where('is_available', true)
                    ->whereHas('menu', function ($menuQuery): void {
                        $menuQuery
                            ->where('status', MenuStatus::Published)
                            ->whereHas('business', fn ($businessQuery) => $businessQuery->where('status', 'active'));
                    });
            })
            ->firstOrFail();

        $business = $video->product->menu->business;
        abort_unless($business instanceof Business, 404);

        $entitlements = new \App\Modules\Subscription\Services\EntitlementService($business);
        abort_unless($entitlements->hasFeature('product_video'), 404);

        $media = $video->media;
        $disk = Storage::disk($media->disk);
        abort_unless($disk->exists($media->path), 404);

        return $disk->response($media->path, basename($media->path), [
            'Content-Type' => 'video/mp4',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=86400, immutable',
        ]);
    }

    private function business(CurrentBusiness $currentBusiness): Business
    {
        $business = $currentBusiness->get();
        abort_unless($business instanceof Business, 404);

        return $business;
    }
}
