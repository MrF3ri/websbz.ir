<?php

namespace App\Modules\Subscription\Actions;

use App\Modules\Business\Models\Business;
use App\Modules\Subscription\Enums\SubscriptionEventType;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Subscription\Models\PlanFeature;
use App\Modules\Subscription\Models\Subscription;
use Illuminate\Support\Facades\DB;

class StartTrialAction
{
    public function execute(Business $business, Plan $plan): Subscription
    {
        return DB::transaction(function () use ($business, $plan): Subscription {
            // Serialize trial creation for this Business, including concurrent onboarding retries.
            $lockedBusiness = Business::query()
                ->whereKey($business->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Reload the plan and its features so the snapshot is based on persisted server-side data.
            $lockedPlan = Plan::query()
                ->with('features')
                ->whereKey($plan->getKey())
                ->firstOrFail();

            $existingSubscription = $lockedBusiness->subscriptions()
                ->latest('id')
                ->first();

            if ($existingSubscription !== null) {
                return $existingSubscription;
            }

            /** @var array<string, mixed> $featuresSnapshot */
            $featuresSnapshot = $lockedPlan->features
                ->mapWithKeys(
                    fn (PlanFeature $feature): array => [$feature->feature_key => $feature->value],
                )
                ->all();

            $startedAt = now();
            $trialEndsAt = $startedAt->copy()->addHours(72);

            $subscription = $lockedBusiness->subscriptions()->create([
                'plan_id' => $lockedPlan->getKey(),
                'status' => SubscriptionStatus::Trialing,
                'features_snapshot' => $featuresSnapshot,
                'trial_ends_at' => $trialEndsAt,
                'current_period_starts_at' => $startedAt,
                'current_period_ends_at' => $trialEndsAt,
            ]);

            $subscription->events()->create([
                'event_type' => SubscriptionEventType::TrialStarted,
                'metadata' => [
                    'plan_id' => $lockedPlan->getKey(),
                    'trial_started_at' => $startedAt->toISOString(),
                    'trial_ends_at' => $trialEndsAt->toISOString(),
                ],
            ]);

            return $subscription;
        }, attempts: 3);
    }
}
