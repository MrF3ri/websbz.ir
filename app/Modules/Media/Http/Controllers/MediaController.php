<?php

namespace App\Modules\Media\Http\Controllers;

use App\Modules\Business\Models\Business;
use App\Modules\Media\Actions\UploadProductImageAction;
use App\Modules\Media\Http\Requests\UploadProductImageRequest;
use App\Modules\Media\Http\Requests\ReorderProductImagesRequest;
use App\Modules\Media\Models\ProductImage;
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

class MediaController
{
    public function store(
        UploadProductImageRequest $request,
        string $productPublicId,
        CurrentBusiness $currentBusiness,
        EnsurePrimaryMenuAction $ensurePrimaryMenu,
        UploadProductImageAction $upload,
        PublicMenuCache $publicMenuCache,
    ): RedirectResponse {
        $business = $this->business($currentBusiness);
        $menu = $ensurePrimaryMenu->execute($business);
        $product = $menu->products()->where('public_id', $productPublicId)->firstOrFail();

        Gate::authorize('update', $product);

        $file = $request->file('image');
        abort_unless($file instanceof UploadedFile, 422);
        $upload->execute($business, $product, $file);
        $publicMenuCache->forget($menu);

        return back()->with('success', 'تصویر محصول با موفقیت بارگذاری شد.');
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

        $image = $product->images()
            ->whereHas('media', fn ($query) => $query->where('public_id', $mediaPublicId))
            ->with('media')
            ->firstOrFail();

        $media = $image->media;

        DB::transaction(function () use ($image, $media): void {
            $image->delete();

            if (! $media->productImages()->exists()) {
                $media->delete();
            }
        });

        if (! $media->fresh()) {
            Storage::disk($media->disk)->delete($media->path);
        }
        $publicMenuCache->forget($menu);

        return back()->with('success', 'تصویر محصول حذف شد.');
    }

    public function reorder(
        ReorderProductImagesRequest $request,
        string $productPublicId,
        CurrentBusiness $currentBusiness,
        EnsurePrimaryMenuAction $ensurePrimaryMenu,
        PublicMenuCache $publicMenuCache,
    ): RedirectResponse {
        $business = $this->business($currentBusiness);
        $menu = $ensurePrimaryMenu->execute($business);
        $product = $menu->products()->where('public_id', $productPublicId)->firstOrFail();
        Gate::authorize('update', $product);
        $publicIds = $request->validated('media_public_ids');

        DB::transaction(function () use ($product, $publicIds): void {
            $lockedProduct = Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            $images = $lockedProduct->images()->whereHas('media', fn ($query) => $query->whereIn('public_id', $publicIds))->with('media')->get();

            if ($images->count() !== count($publicIds)) {
                abort(404);
            }

            foreach ($publicIds as $index => $publicId) {
                $image = $images->first(fn (ProductImage $candidate): bool => $candidate->media->public_id === $publicId);
                $image?->update(['sort_order' => $index]);
            }
        });

        $publicMenuCache->forget($menu);

        return back()->with('success', 'ترتیب تصاویر ذخیره شد.');
    }

    public function publicImage(string $mediaPublicId): StreamedResponse
    {
        $image = ProductImage::query()
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

        $media = $image->media;
        abort_unless(Storage::disk($media->disk)->exists($media->path), 404);

        return Storage::disk($media->disk)->response($media->path, basename($media->path), [
            'Content-Type' => $media->mime_type,
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
