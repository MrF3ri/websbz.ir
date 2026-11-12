<?php

namespace App\Modules\Subscription\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $monthly_price
 * @property bool $is_active
 *
 * @method static Builder<static> query()
 */
class Plan extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'code',
        'name',
        'monthly_price',
        'is_active',
    ];

    /**
     * @return HasMany<PlanFeature, $this>
     */
    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'monthly_price' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
