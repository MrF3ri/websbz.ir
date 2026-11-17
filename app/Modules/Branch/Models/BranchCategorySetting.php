<?php

namespace App\Modules\Branch\Models;

use App\Modules\Category\Models\Category;
use App\Modules\Menu\Models\Menu;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $branch_id
 * @property int $business_id
 * @property int $menu_id
 * @property int $category_id
 * @property bool $is_visible
 * @property int $sort_order
 */
class BranchCategorySetting extends Model
{
    protected $fillable = [
        'branch_id',
        'business_id',
        'menu_id',
        'category_id',
        'is_visible',
        'sort_order',
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

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_visible' => 'boolean', 'sort_order' => 'integer'];
    }
}
