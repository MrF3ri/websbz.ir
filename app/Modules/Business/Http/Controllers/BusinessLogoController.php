<?php

namespace App\Modules\Business\Http\Controllers;

use App\Modules\Business\Actions\UploadBusinessLogoAction;
use App\Modules\Business\Http\Requests\UploadBusinessLogoRequest;
use App\Modules\Business\Models\Business;
use App\Modules\Media\Models\BusinessLogo;
use App\Modules\Media\Models\Media;
use App\Modules\PublicMenu\Services\PublicMenuCache;
use App\Modules\Tenancy\Services\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BusinessLogoController
{
    public function store(
        UploadBusinessLogoRequest $request,
        CurrentBusiness $currentBusiness,
        UploadBusinessLogoAction $upload,
        PublicMenuCache $publicMenuCache,
    ): RedirectResponse {
        $business = $this->business($currentBusiness);
        $file = $request->file('logo');
        abort_unless($file instanceof UploadedFile, 422);
        $upload->execute($business, $file);
        $this->forgetPublicMenu($business, $publicMenuCache);

        return back()->with('success', 'لوگوی کسب‌وکار با موفقیت به‌روزرسانی شد.');
    }

    public function destroy(
        CurrentBusiness $currentBusiness,
        PublicMenuCache $publicMenuCache,
    ): RedirectResponse {
        $business = $this->business($currentBusiness);
        Gate::authorize('update', $business);
        DB::transaction(function () use ($business): void {
            $lockedBusiness = Business::query()
                ->whereKey($business->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $oldLogo = $lockedBusiness->logo()->lockForUpdate()->first();
            $oldMedia = $oldLogo?->media()->lockForUpdate()->first();

            if ($oldMedia instanceof Media) {
                $this->deleteMedia($oldMedia);
            }

            $oldLogo?->delete();
        });

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

        $logo = BusinessLogo::query()
            ->with('media')
            ->where('business_id', $business->getKey())
            ->whereHas('media', fn ($query) => $query->where('public_id', $mediaPublicId))
            ->firstOrFail();
        $media = $logo->media;

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

        $disk = Storage::disk($media->disk);

        if ($disk->exists($media->path) && ! $disk->delete($media->path)) {
            throw new RuntimeException('Unable to delete business logo from storage.');
        }

        $media->delete();
    }
}
