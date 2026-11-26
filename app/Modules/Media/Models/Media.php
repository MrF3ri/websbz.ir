<?php

namespace App\Modules\Media\Models;

use App\Modules\Business\Models\Business;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property int $business_id
 * @property string $disk
 * @property string $path
 * @property string $mime_type
 * @property int $size
 * @property int $width
 * @property int $height
 * @property int|null $duration_ms
 * @property array<string, array{path: string, width: int, height: int, mime_type: string}>|null $variants
 */
class Media extends Model
{
    protected $fillable = [
        'business_id',
        'disk',
        'path',
        'mime_type',
        'size',
        'width',
        'height',
        'duration_ms',
        'variants',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $media): void {
            if (blank($media->public_id)) {
                $media->public_id = (string) Str::ulid();
            }
        });
    }

    /** @return BelongsTo<Business, $this> */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /** @return HasMany<ProductImage, $this> */
    public function productImages(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'duration_ms' => 'integer',
            'variants' => 'array',
        ];
    }
}
