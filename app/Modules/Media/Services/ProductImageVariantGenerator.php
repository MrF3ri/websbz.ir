<?php

namespace App\Modules\Media\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ProductImageVariantGenerator
{
    /**
     * @return array<string, array{path: string, width: int, height: int, mime_type: string}>
     */
    public function generate(UploadedFile $file, string $mimeType, string $directory, string $baseName, string $disk): array
    {
        $bytes = file_get_contents($file->getPathname());

        if (! is_string($bytes)) {
            throw new RuntimeException('Unable to read uploaded image.');
        }

        $source = @imagecreatefromstring($bytes);

        if ($source === false) {
            throw new RuntimeException('Unable to decode uploaded image.');
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new RuntimeException('Unsupported image format.'),
        };

        if ($mimeType === 'image/webp' && ! function_exists('imagewebp')) {
            imagedestroy($source);
            throw new RuntimeException('WebP encoding is not available on this server.');
        }

        $variants = [];

        try {
            foreach (['thumbnail' => 320, 'medium' => 960] as $name => $maxEdge) {
                $scale = min(1, $maxEdge / max($sourceWidth, $sourceHeight));
                $width = max(1, (int) round($sourceWidth * $scale));
                $height = max(1, (int) round($sourceHeight * $scale));
                $target = imagecreatetruecolor($width, $height);

                if ($mimeType !== 'image/jpeg') {
                    imagealphablending($target, false);
                    imagesavealpha($target, true);
                    $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
                    imagefilledrectangle($target, 0, 0, $width, $height, $transparent);
                }

                imagecopyresampled($target, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);
                ob_start();

                $encoded = match ($mimeType) {
                    'image/jpeg' => imagejpeg($target, null, 82),
                    'image/png' => imagepng($target, null, 6),
                    'image/webp' => imagewebp($target, null, 82),
                };
                $contents = ob_get_clean();
                imagedestroy($target);

                if (! $encoded || ! is_string($contents)) {
                    throw new RuntimeException('Unable to encode responsive image variant.');
                }

                $path = $directory.'/'.$baseName.'-'.$name.'.'.$extension;

                if (! Storage::disk($disk)->put($path, $contents)) {
                    throw new RuntimeException('Unable to store responsive image variant.');
                }

                $variants[$name] = [
                    'path' => $path,
                    'width' => $width,
                    'height' => $height,
                    'mime_type' => $mimeType,
                ];
            }
        } catch (Throwable $exception) {
            foreach ($variants as $variant) {
                Storage::disk($disk)->delete($variant['path']);
            }

            throw $exception;
        } finally {
            imagedestroy($source);
        }

        return $variants;
    }
}
