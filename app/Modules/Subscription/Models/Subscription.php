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
 * @property array<string, mixed>|null $features_snapshot
 * @property string|null $plan_version
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
        'features_snapshot',
        'plan_version',
        'trial_ends_at',
        'current_period_starts_at',
        'current_period_ends_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (Subscription $subscription): void {
            if ($subscription->features_snapshot === null) {
                $plan = Plan::query()
                    ->with('features')
                    ->findOrFail($subscription->plan_id);

                $subscription->features_snapshot = $plan->features
                    ->mapWithKeys(fn (PlanFeature $feature): array => [
                        $feature->feature_key => $feature->value,
                    ])
                    ->all();
            }

            $subscription->plan_version ??= hash(
                'sha256',
                json_encode($subscription->features_snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            );
        });
    }

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
            'features_snapshot' => 'array',
            'trial_ends_at' => 'datetime',
            'current_period_starts_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
        ];
    }
}
