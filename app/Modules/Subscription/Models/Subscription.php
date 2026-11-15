<?php

namespace App\Modules\Subscription\Models;

use App\Modules\Business\Models\Business;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
                $subscription->capturePlanSnapshot();
            } else {
                $subscription->refreshPlanVersion();
            }
        });

        static::updating(function (Subscription $subscription): void {
            if ($subscription->isDirty('plan_id') && ! $subscription->isDirty('features_snapshot')) {
                // Capture the plan when callers do not supply an explicit contractual snapshot.
                $subscription->capturePlanSnapshot();

                return;
            }

            if ($subscription->isDirty('features_snapshot')) {
                $subscription->refreshPlanVersion();
            }
        });
    }

    private function capturePlanSnapshot(): void
    {
        $plan = Plan::query()
            ->with('features')
            ->findOrFail($this->plan_id);

        $this->features_snapshot = $plan->features
            ->mapWithKeys(fn (PlanFeature $feature): array => [
                $feature->feature_key => $feature->value,
            ])
            ->all();

        $this->refreshPlanVersion();
    }

    private function refreshPlanVersion(): void
    {
        $this->plan_version = hash(
            'sha256',
            json_encode($this->features_snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        );
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

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<SubscriptionEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(SubscriptionEvent::class);
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
