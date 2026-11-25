<?php

namespace App\Modules\Media\Models;

use App\Modules\Product\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $business_id
 * @property int $menu_id
 * @property int $product_id
 * @property int $media_id
 * @property int $sort_order
 */
class ProductImage extends Model
{
    protected $fillable = [
        'business_id',
        'menu_id',
        'product_id',
        'media_id',
        'sort_order',
    ];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Media, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }
}
