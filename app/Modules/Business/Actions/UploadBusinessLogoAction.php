<?php

namespace App\Modules\Business\Actions;

use App\Modules\Business\Models\Business;
use App\Modules\Media\Models\Media;
use App\Modules\Subscription\Services\EntitlementService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class UploadBusinessLogoAction
{
    public function execute(Business $business, UploadedFile $file): Media
    {
        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getPathname());
        $extension = strtolower($file->getClientOriginalExtension());
        $allowedExtensions = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/webp' => ['webp'],
        ];

        if (! is_string($mimeType) || ! in_array($extension, $allowedExtensions[$mimeType] ?? [], true)) {
            throw ValidationException::withMessages([
                'logo' => 'نوع واقعی تصویر و پسوند فایل با یکدیگر مطابقت ندارند.',
            ]);
        }

        $dimensions = @getimagesize($file->getPathname());

        if (
            ! is_array($dimensions)
            || $dimensions[0] < 1
            || $dimensions[1] < 1
            || $dimensions[0] > 2048
            || $dimensions[1] > 2048
        ) {
            throw ValidationException::withMessages([
                'logo' => 'ابعاد لوگو معتبر نیست یا از حداکثر ۲۰۴۸ پیکسل بیشتر است.',
            ]);
        }

        $disk = (string) config('filesystems.media_disk', 'media');
        $filename = (string) Str::ulid().'.'.$extension;
        $directory = 'businesses/'.$business->public_id.'/logo';
        $storedPath = null;
        $oldMedia = null;

        try {
            $newMedia = DB::transaction(function () use (
                $business,
                $file,
                $mimeType,
                $dimensions,
                $disk,
                $directory,
                $filename,
                &$storedPath,
                &$oldMedia,
            ): Media {
                $lockedBusiness = Business::query()
                    ->whereKey($business->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! (new EntitlementService($lockedBusiness))->hasFeature('business_logo')) {
                    abort(403);
                }

                $storedPath = Storage::disk($disk)->putFileAs($directory, $file, $filename);

                if (! is_string($storedPath) || $storedPath === '') {
                    throw new RuntimeException('Unable to store business logo.');
                }

                $oldMedia = $lockedBusiness->logo()->lockForUpdate()->first();

                $media = Media::query()->create([
                    'business_id' => $lockedBusiness->getKey(),
                    'disk' => $disk,
                    'path' => $storedPath,
                    'mime_type' => $mimeType,
                    'size' => $file->getSize(),
                    'width' => (int) $dimensions[0],
                    'height' => (int) $dimensions[1],
                ]);

                $lockedBusiness->update(['logo_media_id' => $media->getKey()]);

                return $media;
            });
        } catch (Throwable $exception) {
            if (is_string($storedPath)) {
                try {
                    Storage::disk($disk)->delete($storedPath);
                } catch (Throwable $cleanupException) {
                    Log::warning('Failed to clean up a business logo after an upload error.', [
                        'disk' => $disk,
                        'path' => $storedPath,
                        'exception' => $cleanupException::class,
                    ]);
                }
            }

            throw $exception;
        }

        if ($oldMedia instanceof Media) {
            $this->deleteReplacedMedia($oldMedia);
        }

        return $newMedia;
    }

    private function deleteReplacedMedia(Media $media): void
    {
        if ($media->productImages()->exists()) {
            return;
        }

        try {
            $disk = Storage::disk($media->disk);

            if ($disk->exists($media->path) && ! $disk->delete($media->path)) {
                throw new RuntimeException('Unable to delete replaced business logo.');
            }

            $media->delete();
        } catch (Throwable $exception) {
            Log::warning('Unable to clean up a replaced business logo; metadata retained for retry.', [
                'media_id' => $media->getKey(),
                'disk' => $media->disk,
                'path' => $media->path,
                'exception' => $exception::class,
            ]);
        }
    }
}
