<?php

namespace App\Modules\Business\Http\Controllers;

use App\Modules\Business\Actions\UploadBusinessLogoAction;
use App\Modules\Business\Http\Requests\UploadBusinessLogoRequest;
use App\Modules\Business\Models\Business;
use App\Modules\Media\Models\Media;
use App\Modules\PublicMenu\Services\PublicMenuCache;
use App\Modules\Tenancy\Services\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class BusinessLogoController
{
    public function store(
        UploadBusinessLogoRequest $request,
        CurrentBusiness $currentBusiness,
        UploadBusinessLogoAction $upload,
        PublicMenuCache $publicMenuCache,
    ): RedirectResponse {
        $business = $this->business($currentBusiness);
        $upload->execute($business, $request->file('logo'));
        $this->forgetPublicMenu($business, $publicMenuCache);

        return back()->with('success', 'لوگوی کسب‌وکار با موفقیت به‌روزرسانی شد.');
    }

    public function destroy(
        CurrentBusiness $currentBusiness,
        PublicMenuCache $publicMenuCache,
    ): RedirectResponse {
        $business = $this->business($currentBusiness);
        Gate::authorize('update', $business);
        $oldMedia = null;

        DB::transaction(function () use ($business, &$oldMedia): void {
            $lockedBusiness = Business::query()
                ->whereKey($business->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $oldMedia = $lockedBusiness->logo()->lockForUpdate()->first();
            $lockedBusiness->update(['logo_media_id' => null]);
        });

        if ($oldMedia instanceof Media) {
            $this->deleteMedia($oldMedia);
        }

        $this->forgetPublicMenu($business, $publicMenuCache);

        return back()->with('success', 'لوگوی کسب‌وکار حذف شد.');
    }

    public function public(string $businessPublicId, string $mediaPublicId): StreamedResponse
    {
        $business = Business::query()
            ->where('public_id', $businessPublicId)
            ->where('status', 'active')
            ->whereHas('menu', fn ($query) => $query->where('status', 'published'))
            ->firstOrFail();

        $media = Media::query()
            ->where('business_id', $business->getKey())
            ->where('public_id', $mediaPublicId)
            ->whereKey($business->logo_media_id)
            ->firstOrFail();

        $disk = Storage::disk($media->disk);
        abort_unless($disk->exists($media->path), 404);

        return $disk->response($media->path, basename($media->path), [
            'Content-Type' => $media->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    private function business(CurrentBusiness $currentBusiness): Business
    {
        $business = $currentBusiness->get();
        abort_unless($business instanceof Business, 404);

        return $business;
    }

    private function forgetPublicMenu(Business $business, PublicMenuCache $cache): void
    {
        $menu = $business->menu()->first();

        if ($menu !== null) {
            $cache->forget($menu);
        }
    }

    private function deleteMedia(Media $media): void
    {
        if ($media->productImages()->exists()) {
            return;
        }

        try {
            $disk = Storage::disk($media->disk);

            if ($disk->exists($media->path) && ! $disk->delete($media->path)) {
                throw new RuntimeException('Unable to delete business logo from storage.');
            }

            $media->delete();
        } catch (Throwable $exception) {
            Log::warning('Unable to delete business logo; metadata retained for retry.', [
                'media_id' => $media->getKey(),
                'disk' => $media->disk,
                'path' => $media->path,
                'exception' => $exception::class,
            ]);
        }
    }
}
