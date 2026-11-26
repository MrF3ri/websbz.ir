<?php

namespace App\\Modules\\Review\\Models;

use App\\Models\\User;
use App\\Modules\\Business\\Models\\Business;
use App\\Modules\\Menu\\Models\\Menu;
use App\\Modules\\Product\\Models\\Product;
use App\\Modules\\Review\\Enums\\ReviewStatus;
use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;
use Illuminate\\Support\\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property int $business_id
 * @property int $menu_id
 * @property int $product_id
 * @property int $rating
 * @property string|null $display_name
 * @property string|null $body
 * @property ReviewStatus $status
 * @property string $visitor_key_hash
 */
class ProductReview extends Model
{
    protected $fillable = [
        'business_id',
        'menu_id',
        'product_id',
        'rating',
        'display_name',
        'body',
        'status',
        'visitor_key_hash',
        'moderated_by',
        'moderated_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $review): void {
            if (blank($review->public_id)) {
                $review->public_id = (string) Str::ulid();
            }
        });
    }

    /** @return BelongsTo<Business, $this> */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
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

    /** @return BelongsTo<User, $this> */
    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'status' => ReviewStatus::class,
            'moderated_at' => 'datetime',
        ];
    }
}
