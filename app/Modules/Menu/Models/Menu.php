<?php

namespace App\Modules\Menu\Models;

use App\Modules\Business\Models\Business;
use App\Modules\Category\Models\Category;
use App\Modules\Menu\Enums\MenuStatus;
use App\Modules\Product\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property int $business_id
 * @property string $title
 * @property string $slug
 * @property MenuStatus $status
 * @property string $default_locale
 * @property array<string, string>|null $theme
 * @property array{title: string|null, message: string|null, cta_label: string|null, cta_url: string|null}|null $advertising_banner
 */
class Menu extends Model
{
    protected $fillable = [
        'business_id',
        'title',
        'slug',
        'status',
        'default_locale',
        'theme',
        'advertising_banner',
    ];

    protected $attributes = [
        'status' => MenuStatus::Draft->value,
        'default_locale' => 'fa',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $menu): void {
            if (blank($menu->public_id)) {
                $menu->public_id = (string) Str::ulid();
            }
        });
    }

    /** @return BelongsTo<Business, $this> */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /** @return HasMany<Category, $this> */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** @return HasMany<MenuTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(MenuTranslation::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => MenuStatus::class,
            'theme' => 'array',
            'advertising_banner' => 'array',
        ];
    }
}
