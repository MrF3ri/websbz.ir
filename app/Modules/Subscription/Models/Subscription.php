<?php

namespace App\Modules\Subscription\Models;

use App\Modules\Business\Models\Business;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $business_id
 * @property int $plan_id
 * @property \App\Modules\Subscription\Enums\SubscriptionStatus $status
 * @property \Illuminate\Support\Carbon|null $trial_ends_at
 * @property \Illuminate\Support\Carbon|null $current_period_starts_at
 * @property \Illuminate\Support\Carbon|null $current_period_ends_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 */
class Subscription extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'business_id',
        'plan_id',
        'status',
        'trial_ends_at',
        'current_period_starts_at',
        'current_period_ends_at',
    ];

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Modules\Business\Models\Business, $this> */
    public function business(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Modules\Subscription\Models\Plan, $this> */
    public function plan(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'trial_ends_at' => 'datetime',
            'current_period_starts_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
        ];
    }
}
