<?php

namespace App\Modules\Media\Actions;

use App\Modules\Business\Models\Business;
use App\Modules\Media\Models\Media;
use App\Modules\Media\Models\ProductImage;
use App\Modules\Media\Services\ProductImageVariantGenerator;
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

class UploadProductImageAction
{
    public function __construct(
        private readonly ProductImageVariantGenerator $variantGenerator,
    ) {}

    public function execute(Business $business, Product $product, UploadedFile $file): ProductImage
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
                'image' => 'نوع واقعی تصویر و پسوند فایل با یکدیگر مطابقت ندارند.',
            ]);
        }

        $dimensions = @getimagesize($file->getPathname());

        if (
            ! is_array($dimensions)
            || $dimensions[0] < 1
            || $dimensions[1] < 1
            || $dimensions[0] > 6000
            || $dimensions[1] > 6000
        ) {
            throw ValidationException::withMessages([
                'image' => 'ابعاد تصویر معتبر نیست یا از حداکثر ۶۰۰۰ پیکسل بیشتر است.',
            ]);
        }

        $menu = $product->menu()->firstOrFail();
        abort_unless((int) $menu->business_id === (int) $business->getKey(), 404);

        $disk = (string) config('filesystems.media_disk', 'media');
        $publicId = (string) Str::ulid();
        $storedPaths = [];

        try {
            return DB::transaction(function () use (
                $business,
                $menu,
                $product,
                $file,
                $mimeType,
                $extension,
                $dimensions,
                $disk,
                $publicId,
                &$storedPaths,
            ): ProductImage {
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
                $enforcement->requireLimit(
                    $lockedBusiness,
                    'max_product_images',
                    $lockedProduct->images()->count(),
                );

                $directory = sprintf(
                    'businesses/%s/products/%s',
                    $lockedBusiness->public_id,
                    $lockedProduct->public_id,
                );
                $filename = $publicId.'.'.$extension;
                $path = $directory.'/'.$filename;
                $storedPaths[] = $path;
                $storedPath = Storage::disk($disk)->putFileAs($directory, $file, $filename);

                if (! is_string($storedPath) || $storedPath === '') {
                    throw new RuntimeException('Unable to store product image.');
                }

                $path = $storedPath;
                $variants = $this->variantGenerator->generate($file, $mimeType, $directory, $publicId, $disk);

                foreach ($variants as $variant) {
                    $storedPaths[] = $variant['path'];
                }

                $media = Media::query()->create([
                    'business_id' => $lockedBusiness->getKey(),
                    'disk' => $disk,
                    'path' => $path,
                    'mime_type' => $mimeType,
                    'size' => $file->getSize(),
                    'width' => (int) $dimensions[0],
                    'height' => (int) $dimensions[1],
                    'variants' => $variants,
                ]);

                $sortOrder = (int) ($lockedProduct->images()->max('sort_order') ?? -1) + 1;

                return $lockedProduct->images()->create([
                    'business_id' => $lockedBusiness->getKey(),
                    'menu_id' => $lockedMenu->getKey(),
                    'media_id' => $media->getKey(),
                    'sort_order' => $sortOrder,
                ]);
            });
        } catch (Throwable $exception) {
            foreach (array_reverse($storedPaths) as $storedPath) {
                try {
                    $cleanupDisk = Storage::disk($disk);

                    if ($cleanupDisk->exists($storedPath) && ! $cleanupDisk->delete($storedPath)) {
                        Log::warning('Failed to remove a stored product image after a database error.', [
                            'disk' => $disk,
                            'path' => $storedPath,
                        ]);
                    }
                } catch (Throwable $cleanupException) {
                    Log::warning('Failed to clean up a product image upload after an error.', [
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
