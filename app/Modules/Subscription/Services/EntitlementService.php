<?php

namespace App\Modules\Subscription\Services;

use App\Modules\Business\Models\Business;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use App\Modules\Subscription\Models\Subscription;
use Illuminate\Support\Carbon;

class EntitlementService
{
    private bool $subscriptionResolved = false;

    private ?Subscription $activeSubscription = null;

    public function __construct(
        private readonly Business $business,
    ) {}

    /**
     * Determine whether this service is bound to the supplied business.
     */
    public function isForBusiness(Business $business): bool
    {
        return $this->business->is($business);
    }

    /**
     * Determine whether the active subscription snapshot enables a boolean feature.
     */
    public function hasFeature(string $featureKey): bool
    {
        $value = $this->activeSubscription()?->features_snapshot[$featureKey] ?? null;

        return $value === true;
    }

    /**
     * Return a numeric limit. -1 means unlimited; null means unavailable or undefined.
     */
    public function getLimit(string $featureKey): int|float|null
    {
        $value = $this->activeSubscription()?->features_snapshot[$featureKey] ?? null;

        if (! is_int($value) && ! is_float($value)) {
            return null;
        }

        return $value;
    }

    private function activeSubscription(): ?Subscription
    {
        if ($this->subscriptionResolved) {
            return $this->activeSubscription;
        }

        $this->activeSubscription = $this->business
            ->subscriptions()
            ->with('plan:id,is_active')
            ->latest('id')
            ->first();

        $this->subscriptionResolved = true;

        $subscription = $this->activeSubscription;

        if (
            ! $subscription
            || ! $this->isValid($subscription)
            || ! $subscription->plan?->is_active
            || ! is_array($subscription->features_snapshot)
        ) {
            $this->activeSubscription = null;
        }

        return $this->activeSubscription;
    }

    private function isValid(Subscription $subscription): bool
    {
        $now = Carbon::now();

        if (
            $subscription->current_period_starts_at === null
            || $subscription->current_period_ends_at === null
        ) {
            return false;
        }

        if (
            $subscription->current_period_starts_at->isAfter($now)
            || $subscription->current_period_ends_at->isPast()
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
