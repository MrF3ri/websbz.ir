<?php

namespace App\Modules\Subscription\Actions;

use App\Models\User;
use App\Modules\Subscription\Enums\PaymentStatus;
use App\Modules\Subscription\Enums\SubscriptionEventType;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use App\Modules\Subscription\Models\Payment;
use App\Modules\Subscription\Models\PlanFeature;
use App\Modules\Subscription\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewManualPaymentAction
{
    public function approve(Payment $payment, User $reviewer): Payment
    {
        return $this->review($payment, $reviewer, true, null);
    }

    public function reject(Payment $payment, User $reviewer, string $reason): Payment
    {
        return $this->review($payment, $reviewer, false, $reason);
    }

    private function review(
        Payment $payment,
        User $reviewer,
        bool $approve,
        ?string $reason,
    ): Payment {
        return DB::transaction(function () use ($payment, $reviewer, $approve, $reason): Payment {
            $lockedPayment = Payment::query()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $finalStatus = $approve ? PaymentStatus::Approved : PaymentStatus::Rejected;

            if ($lockedPayment->status === $finalStatus) {
                return $lockedPayment;
            }

            if ($lockedPayment->status !== PaymentStatus::PendingReview) {
                throw ValidationException::withMessages([
                    'payment' => 'این پرداخت قبلاً تعیین تکلیف شده است.',
                ]);
            }

            $subscription = Subscription::query()
                ->whereKey($lockedPayment->subscription_id)
                ->lockForUpdate()
                ->firstOrFail();

            $now = now();

            if (! $approve) {
                $lockedPayment->update([
                    'status' => PaymentStatus::Rejected,
                    'reviewed_by' => $reviewer->getKey(),
                    'reviewed_at' => $now,
                    'rejection_reason' => $reason,
                ]);

                $subscription->events()->create([
                    'event_type' => SubscriptionEventType::PaymentRejected,
                    'metadata' => [
                        'payment_id' => $lockedPayment->getKey(),
                        'amount' => $lockedPayment->amount,
                        'bank_tracking_number' => $lockedPayment->bank_tracking_number,
                        'reason' => $reason,
                        'reviewed_by' => $reviewer->getKey(),
                    ],
                ]);

                return $lockedPayment->refresh();
            }

            $targetPlan = $lockedPayment->targetPlan()
                ->with('features')
                ->firstOrFail();

            /** @var array<string, mixed> $featuresSnapshot */
            $featuresSnapshot = $lockedPayment->target_features_snapshot
                ?? $targetPlan->features
                    ->mapWithKeys(
                        fn (PlanFeature $feature): array => [$feature->feature_key => $feature->value],
                    )
                    ->all();

            $previousPlan = $subscription->plan()->firstOrFail();
            $previousPlanId = $previousPlan->getKey();
            $periodBase = $now;

            foreach ([$subscription->trial_ends_at, $subscription->current_period_ends_at] as $candidate) {
                if ($candidate !== null && $candidate->greaterThan($periodBase)) {
                    $periodBase = $candidate;
                }
            }

            $preserveCurrentAccessPeriod = (
                $subscription->status === SubscriptionStatus::Trialing
                && $subscription->trial_ends_at !== null
                && $subscription->trial_ends_at->greaterThan($now)
                && $subscription->current_period_starts_at !== null
            ) || (
                $subscription->status === SubscriptionStatus::Active
                && $subscription->current_period_starts_at !== null
                && $subscription->current_period_starts_at->lessThanOrEqualTo($now)
                && $subscription->current_period_ends_at !== null
                && $subscription->current_period_ends_at->greaterThan($now)
            );
            $accessPeriodStartsAt = $preserveCurrentAccessPeriod && $subscription->current_period_starts_at !== null
                ? $subscription->current_period_starts_at
                : $periodBase;
            $periodEndsAt = $periodBase->copy()->addMonth();

            $lockedPayment->update([
                'status' => PaymentStatus::Approved,
                'paid_at' => $now,
                'reviewed_by' => $reviewer->getKey(),
                'reviewed_at' => $now,
                'rejection_reason' => null,
            ]);

            $subscription->forceFill([
                'plan_id' => $targetPlan->getKey(),
                'features_snapshot' => $featuresSnapshot,
                'plan_version' => hash(
                    'sha256',
                    json_encode($featuresSnapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                ),
                'status' => SubscriptionStatus::Active,
                'current_period_starts_at' => $accessPeriodStartsAt,
                'current_period_ends_at' => $periodEndsAt,
            ])->saveQuietly();

            $subscription->events()->create([
                'event_type' => SubscriptionEventType::PaymentApproved,
                'metadata' => [
                    'payment_id' => $lockedPayment->getKey(),
                    'amount' => $lockedPayment->amount,
                    'bank_tracking_number' => $lockedPayment->bank_tracking_number,
                    'target_plan_id' => $targetPlan->getKey(),
                    'paid_at' => $now->toISOString(),
                    'current_period_starts_at' => $accessPeriodStartsAt->toISOString(),
                    'paid_period_starts_at' => $periodBase->toISOString(),
                    'current_period_ends_at' => $periodEndsAt->toISOString(),
                    'reviewed_by' => $reviewer->getKey(),
                ],
            ]);

            $changeEvent = match (true) {
                $previousPlanId === $targetPlan->getKey() => SubscriptionEventType::Renewed,
                $targetPlan->monthly_price < $previousPlan->monthly_price => SubscriptionEventType::Downgraded,
                default => SubscriptionEventType::Upgraded,
            };

            $subscription->events()->create([
                'event_type' => $changeEvent,
                'metadata' => [
                    'payment_id' => $lockedPayment->getKey(),
                    'previous_plan_id' => $previousPlanId,
                    'new_plan_id' => $targetPlan->getKey(),
                ],
            ]);

            return $lockedPayment->refresh();
        }, attempts: 3);
    }
}
