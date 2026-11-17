<?php

namespace App\Modules\Branch\Models;

use App\Modules\Menu\Models\Menu;
use App\Modules\Product\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $branch_id
 * @property int $business_id
 * @property int $menu_id
 * @property int $product_id
 * @property string|null $name_override
 * @property string|null $description_override
 * @property bool $description_override_enabled
 * @property int|null $base_price_override
 * @property int|null $discount_price_override
 * @property string $discount_override_mode
 * @property bool $is_visible
 * @property bool $is_available
 * @property bool $track_inventory
 * @property int|null $stock_quantity
 * @property int $sort_order
 */
class BranchProductSetting extends Model
{
    protected $fillable = [
        'branch_id',
        'business_id',
        'menu_id',
        'product_id',
        'name_override',
        'description_override',
        'description_override_enabled',
        'base_price_override',
        'discount_price_override',
        'discount_override_mode',
        'is_visible',
        'is_available',
        'track_inventory',
        'stock_quantity',
        'sort_order',
    ];

    protected $attributes = [
        'description_override_enabled' => false,
        'discount_override_mode' => 'inherit',
        'is_visible' => true,
        'is_available' => true,
        'track_inventory' => false,
        'sort_order' => 0,
    ];

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Menu, $this> */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'description_override_enabled' => 'boolean',
            'base_price_override' => 'integer',
            'discount_price_override' => 'integer',
            'is_visible' => 'boolean',
            'is_available' => 'boolean',
            'track_inventory' => 'boolean',
            'stock_quantity' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
