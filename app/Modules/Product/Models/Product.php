<?php

namespace App\Modules\Product\Models;

use App\Modules\Category\Models\Category;
use App\Modules\Media\Models\ProductImage;
use App\Modules\Menu\Models\Menu;
use App\Modules\Product\Enums\ProductLabel;
use App\Modules\Product\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property int $menu_id
 * @property int $category_id
 * @property string $name
 * @property string|null $description
 * @property ProductLabel|null $label
 * @property int $base_price
 * @property int|null $discount_price
 * @property bool $is_available
 * @property ProductStatus $status
 * @property int $sort_order
 */
class Product extends Model
{
    protected $fillable = [
        'menu_id',
        'category_id',
        'name',
        'description',
        'label',
        'base_price',
        'discount_price',
        'is_available',
        'status',
        'sort_order',
    ];

    protected $attributes = [
        'is_available' => true,
        'status' => ProductStatus::Draft->value,
        'sort_order' => 0,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $product): void {
            if (blank($product->public_id)) {
                $product->public_id = (string) Str::ulid();
            }
        });
    }

    /** @return BelongsTo<Menu, $this> */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<ProductImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<ProductTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(ProductTranslation::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'base_price' => 'integer',
            'discount_price' => 'integer',
            'is_available' => 'boolean',
            'status' => ProductStatus::class,
            'label' => ProductLabel::class,
            'sort_order' => 'integer',
        ];
    }
}
