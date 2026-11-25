<?php

namespace App\Modules\Media\Http\Controllers;

use App\Modules\Business\Models\Business;
use App\Modules\Media\Actions\UploadProductImageAction;
use App\Modules\Media\Http\Requests\ReorderProductImagesRequest;
use App\Modules\Media\Http\Requests\UploadProductImageRequest;
use App\Modules\Media\Models\Media;
use App\Modules\Media\Models\ProductImage;
use App\Modules\Menu\Actions\EnsurePrimaryMenuAction;
use App\Modules\Menu\Enums\MenuStatus;
use App\Modules\Product\Enums\ProductStatus;
use App\Modules\Product\Models\Product;
use App\Modules\PublicMenu\Services\PublicMenuCache;
use App\Modules\Tenancy\Services\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
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
            ->firstOrFail();

        DB::transaction(function () use ($image): void {
            $lockedImage = ProductImage::query()
                ->whereKey($image->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $media = Media::query()
                ->whereKey($lockedImage->media_id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedImage->delete();

            if ($media->productImages()->exists()) {
                return;
            }

            $paths = [$media->path];

            foreach ($media->variants ?? [] as $variant) {
                $paths[] = $variant['path'];
            }

            foreach ($paths as $path) {
                $disk = Storage::disk($media->disk);

                if ($disk->exists($path) && ! $disk->delete($path)) {
                    throw new RuntimeException('Unable to delete product image from storage.');
                }
            }

            $media->delete();
        });

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

            if ($images->count() !== count($publicIds) || $images->count() !== $lockedProduct->images()->count()) {
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

    public function publicImage(string $mediaPublicId, Request $request): StreamedResponse
    {
        $variantName = $request->query('variant', 'original');
        abort_unless(is_string($variantName) && in_array($variantName, ['thumbnail', 'medium', 'original'], true), 404);

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
        $path = $media->path;
        $mimeType = $media->mime_type;

        if ($variantName !== 'original') {
            $variant = $media->variants[$variantName] ?? null;
            abort_unless(is_array($variant), 404);
            $path = $variant['path'];
            $mimeType = $variant['mime_type'];
        }

        $disk = Storage::disk($media->disk);
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, basename($path), [
            'Content-Type' => $mimeType,
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
