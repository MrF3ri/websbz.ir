<?php

namespace App\Modules\Subscription\Services;

use App\Modules\Business\Models\Business;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Subscription\Models\Subscription;
use Illuminate\Support\Carbon;

class EntitlementService
{
    private bool $subscriptionResolved = false;

    private ?Subscription $currentSubscription = null;

    public function __construct(
        private readonly Business $business,
    ) {}

    /**
     * Determine whether the current plan enables a boolean feature.
     */
    public function hasFeature(string $featureKey): bool
    {
        $value = $this->currentPlan()
            ?->features
            ->firstWhere('feature_key', $featureKey)
            ?->value;

        return $value === true;
    }

    /**
     * Return a numeric feature limit, or null when it is unlimited/not defined.
     */
    public function getLimit(string $featureKey): int|float|null
    {
        $value = $this->currentPlan()
            ?->features
            ->firstWhere('feature_key', $featureKey)
            ?->value;

        if (! is_numeric($value)) {
            return null;
        }

        $limit = $value + 0;

        // -1 is the specification's sentinel for an unlimited allowance.
        if ($limit === -1) {
            return null;
        }

        return $limit;
    }

    private function currentPlan(): ?Plan
    {
        if (! $this->subscriptionResolved) {
            $this->currentSubscription = $this->business
                ->subscriptions()
                ->with('plan.features')
                ->latest('id')
                ->first();

            $this->subscriptionResolved = true;
        }

        $subscription = $this->currentSubscription;

        if (! $subscription || ! $this->isValid($subscription)) {
            return null;
        }

        $plan = $subscription->plan;

        return $plan instanceof Plan && $plan->is_active ? $plan : null;
    }

    private function isValid(Subscription $subscription): bool
    {
        $now = Carbon::now();

        if (
            $subscription->current_period_starts_at !== null
            && $subscription->current_period_starts_at->isAfter($now)
        ) {
            return false;
        }

        if (
            $subscription->current_period_ends_at !== null
            && $subscription->current_period_ends_at->isPast()
        ) {
            return false;
        }

        return match ($subscription->status) {
            SubscriptionStatus::Active => true,
            SubscriptionStatus::Trialing => $subscription->trial_ends_at !== null
                && $subscription->trial_ends_at->isFuture(),
            default => false,
        };
    }
}
