<?php

namespace App\Modules\Media\Actions;

use App\Modules\Business\Models\Business;
use App\Modules\Media\Models\Media;
use App\Modules\Media\Models\ProductVideo;
use App\Modules\Media\Services\Mp4DurationReader;
use App\Modules\Menu\Models\Menu;
use App\Modules\Product\Models\Product;
use App\Modules\Subscription\Services\EnforcementService;
use App\Modules\Subscription\Services\EntitlementService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class UploadProductVideoAction
{
    private const MAX_SIZE_BYTES = 10 * 1024 * 1024;

    private const MAX_DURATION_MS = 5000;

    public function __construct(
        private readonly Mp4DurationReader $durationReader,
    ) {}

    public function execute(Business $business, Product $product, UploadedFile $file): ProductVideo
    {
        $fileSize = $file->getSize();

        if (! is_int($fileSize) || $fileSize > self::MAX_SIZE_BYTES) {
            throw ValidationException::withMessages([
                'video' => 'حجم ویدئو نباید بیشتر از ۱۰ مگابایت باشد.',
            ]);
        }

        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getPathname());
        $extension = strtolower($file->getClientOriginalExtension());

        if ($mimeType !== 'video/mp4' || $extension !== 'mp4') {
            throw ValidationException::withMessages([
                'video' => 'فقط فایل MP4 معتبر پذیرفته می‌شود.',
            ]);
        }

        try {
            $durationMs = $this->durationReader->milliseconds($file->getPathname());
        } catch (RuntimeException) {
            throw ValidationException::withMessages([
                'video' => 'ساختار ویدئو معتبر نیست یا اطلاعات مدت آن قابل خواندن نیست.',
            ]);
        }

        if ($durationMs < 1 || $durationMs > self::MAX_DURATION_MS) {
            throw ValidationException::withMessages([
                'video' => 'مدت ویدئو نباید بیشتر از ۵ ثانیه باشد.',
            ]);
        }

        $menu = $product->menu()->firstOrFail();
        abort_unless((int) $menu->business_id === (int) $business->getKey(), 404);

        $disk = (string) config('filesystems.media_disk', 'media');
        $publicId = (string) Str::ulid();
        $storedPath = null;

        try {
            return DB::transaction(function () use (
                $business,
                $menu,
                $product,
                $file,
                $mimeType,
                $durationMs,
                $disk,
                $publicId,
                &$storedPath,
            ): ProductVideo {
                $lockedBusiness = Business::query()
                    ->whereKey($business->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedMenu = Menu::query()
                    ->whereKey($menu->getKey())
                    ->where('business_id', $lockedBusiness->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedProduct = Product::query()
                    ->whereKey($product->getKey())
                    ->where('menu_id', $lockedMenu->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $enforcement = new EnforcementService(new EntitlementService($lockedBusiness));
                $enforcement->requireFeature($lockedBusiness, 'product_video');
                $enforcement->requireLimit(
                    $lockedBusiness,
                    'max_product_videos',
                    $lockedProduct->videos()->count(),
                );

                $directory = sprintf(
                    'businesses/%s/products/%s/videos',
                    $lockedBusiness->public_id,
                    $lockedProduct->public_id,
                );
                $filename = $publicId.'.mp4';
                $storedPath = $directory.'/'.$filename;
                $actualPath = Storage::disk($disk)->putFileAs($directory, $file, $filename);

                if (! is_string($actualPath) || $actualPath === '') {
                    throw new RuntimeException('Unable to store product video.');
                }

                $media = Media::query()->create([
                    'business_id' => $lockedBusiness->getKey(),
                    'disk' => $disk,
                    'path' => $actualPath,
                    'mime_type' => $mimeType,
                    'size' => $fileSize,
                    'width' => 0,
                    'height' => 0,
                    'duration_ms' => $durationMs,
                    'variants' => null,
                ]);

                return $lockedProduct->videos()->create([
                    'business_id' => $lockedBusiness->getKey(),
                    'menu_id' => $lockedMenu->getKey(),
                    'media_id' => $media->getKey(),
                ]);
            });
        } catch (Throwable $exception) {
            if (is_string($storedPath)) {
                try {
                    $storage = Storage::disk($disk);

                    if ($storage->exists($storedPath) && ! $storage->delete($storedPath)) {
                        Log::warning('Failed to remove a product video after a database error.', [
                            'disk' => $disk,
                            'path' => $storedPath,
                        ]);
                    }
                } catch (Throwable $cleanupException) {
                    Log::warning('Failed to clean up a product video upload after an error.', [
                        'disk' => $disk,
                        'path' => $storedPath,
                        'exception' => $cleanupException::class,
                    ]);
                }
            }

            throw $exception;
        }
    }
}
