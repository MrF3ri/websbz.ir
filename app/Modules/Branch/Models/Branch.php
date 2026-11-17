<?php

namespace App\Modules\Branch\Models;

use App\Modules\Business\Models\Business;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property int $business_id
 * @property string $name
 * @property string $slug
 * @property string $status
 * @property bool $is_primary
 * @property int|null $primary_business_id
 * @property array<string, mixed>|null $opening_hours
 */
class Branch extends Model
{
    protected $fillable = [
        'business_id',
        'name',
        'slug',
        'status',
        'is_primary',
        'primary_business_id',
        'phone',
        'email',
        'address',
        'latitude',
        'longitude',
        'timezone',
        'opening_hours',
    ];

    protected $attributes = [
        'status' => 'active',
        'is_primary' => false,
        'timezone' => 'Asia/Tehran',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $branch): void {
            $branch->primary_business_id = $branch->is_primary ? $branch->business_id : null;
        });

        static::creating(function (self $branch): void {
            if (blank($branch->public_id)) {
                $branch->public_id = (string) Str::ulid();
            }
        });
    }

    /** @return BelongsTo<Business, $this> */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /** @return HasMany<BranchCategorySetting, $this> */
    public function categorySettings(): HasMany
    {
        return $this->hasMany(BranchCategorySetting::class);
    }

    /** @return HasMany<BranchProductSetting, $this> */
    public function productSettings(): HasMany
    {
        return $this->hasMany(BranchProductSetting::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'opening_hours' => 'array',
        ];
    }
}
