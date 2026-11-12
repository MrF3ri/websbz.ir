<?php

namespace App\Modules\Subscription\Models;

use App\Modules\Business\Models\Business;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $business_id
 * @property int $plan_id
 * @property SubscriptionStatus $status
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $current_period_starts_at
 * @property Carbon|null $current_period_ends_at
 *
 * @method static Builder<static> query()
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

    /** @return BelongsTo<Business, $this> */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
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
