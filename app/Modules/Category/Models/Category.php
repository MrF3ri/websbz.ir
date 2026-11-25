<?php

namespace App\Modules\Category\Models;

use App\Modules\Branch\Models\BranchCategorySetting;
use App\Modules\Menu\Models\Menu;
use App\Modules\Product\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property int $menu_id
 * @property string $name
 * @property int $sort_order
 * @property bool $is_active
 * @property int $products_count
 */
class Category extends Model
{
    protected $fillable = ['menu_id', 'name', 'sort_order', 'is_active'];

    protected $attributes = [
        'sort_order' => 0,
        'is_active' => true,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $category): void {
            if (blank($category->public_id)) {
                $category->public_id = (string) Str::ulid();
            }
        });
    }

    /** @return BelongsTo<Menu, $this> */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** @return HasMany<BranchCategorySetting, $this> */
    public function branchSettings(): HasMany
    {
        return $this->hasMany(BranchCategorySetting::class);
    }

    /** @return HasMany<CategoryTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(CategoryTranslation::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean'];
    }
}
