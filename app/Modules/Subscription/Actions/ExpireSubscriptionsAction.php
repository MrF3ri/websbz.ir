<?php

namespace App\\Modules\\Subscription\\Actions;

use App\\Modules\\Subscription\\Enums\\SubscriptionEventType;
use App\\Modules\\Subscription\\Enums\\SubscriptionStatus;
use App\\Modules\\Subscription\\Models\\Subscription;
use Illuminate\\Database\\Eloquent\\Builder;
use Illuminate\\Support\\Facades\\DB;

class ExpireSubscriptionsAction
{
    public function execute(): int
    {
        $now = now();
        $expiredCount = 0;

        Subscription::query()
            ->where(function (Builder $query) use ($now): void {
                $query
                    ->where(function (Builder $trialQuery) use ($now): void {
                        $trialQuery
                            ->where('status', SubscriptionStatus::Trialing->value)
                            ->whereNotNull('trial_ends_at')
                            ->where('trial_ends_at', '<=', $now);
                    })
                    ->orWhere(function (Builder $activeQuery) use ($now): void {
                        $activeQuery
                            ->where('status', SubscriptionStatus::Active->value)
                            ->where('current_period_ends_at', '<=', $now);
                    });
            })
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions) use (&$expiredCount): void {
                foreach ($subscriptions as $candidate) {
                    $expired = DB::transaction(function () use ($candidate): bool {
                        $subscription = Subscription::query()
                            ->whereKey($candidate->getKey())
                            ->lockForUpdate()
                            ->first();

                        if (! $subscription instanceof Subscription) {
                            return false;
                        }

                        $now = now();
                        $previousStatus = $subscription->status;

                        if (
                            $previousStatus === SubscriptionStatus::Trialing
                            && $subscription->trial_ends_at !== null
                            && $subscription->trial_ends_at->lessThanOrEqualTo($now)
                        ) {
                            $subscription->forceFill([
                                'status' => SubscriptionStatus::Expired,
                            ])->save();

                            $subscription->events()->create([
                                'event_type' => SubscriptionEventType::TrialEnded,
                                'metadata' => [
                                    'trial_ends_at' => $subscription->trial_ends_at->toISOString(),
                                    'ended_at' => $now->toISOString(),
                                ],
                            ]);
                        } elseif (
                            $previousStatus === SubscriptionStatus::Active
                            && $subscription->current_period_ends_at !== null
                            && $subscription->current_period_ends_at->lessThanOrEqualTo($now)
                        ) {
                            $subscription->forceFill([
                                'status' => SubscriptionStatus::Expired,
                            ])->save();
                        } else {
                            return false;
                        }

                        $subscription->events()->create([
                            'event_type' => SubscriptionEventType::Expired,
                            'metadata' => [
                                'previous_status' => $previousStatus->value,
                                'expired_at' => $now->toISOString(),
                                'current_period_ends_at' => $subscription->current_period_ends_at?->toISOString(),
                            ],
                        ]);

                        return true;
                    }, attempts: 3);

                    if ($expired) {
                        $expiredCount++;
                    }
                }
            });

        return $expiredCount;
    }
}
