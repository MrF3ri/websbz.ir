<?php

namespace App\Modules\Subscription\Actions;

use App\Modules\Subscription\Contracts\PaymentGatewayInterface;
use App\Modules\Subscription\Enums\GatewayVerificationResult;
use App\Modules\Subscription\Enums\PaymentStatus;
use App\Modules\Subscription\Enums\SubscriptionEventType;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use App\Modules\Subscription\Models\Payment;
use App\Modules\Subscription\Models\PlanFeature;
use App\Modules\Subscription\Models\Subscription;
use Illuminate\Support\Facades\DB;

class ProcessPaymentCallbackAction
{
    public function __construct(
        private readonly PaymentGatewayInterface $paymentGateway,
    ) {}

    public function execute(string $gatewayReference): Payment
    {
        // Avoid unnecessary provider calls for callbacks that have already been finalized.
        $existingPayment = Payment::query()
            ->where('gateway_reference', $gatewayReference)
            ->firstOrFail();

        if ($existingPayment->status !== PaymentStatus::Pending) {
            return $existingPayment;
        }

        // Step A: perform external HTTP verification before opening a transaction or acquiring locks.
        $verificationResult = $this->paymentGateway->verifyPayment($gatewayReference);

        // Step B: apply the verified result atomically, rechecking state under lock.
        return DB::transaction(function () use ($gatewayReference, $verificationResult): Payment {
            $payment = Payment::query()
                ->where('gateway_reference', $gatewayReference)
                ->lockForUpdate()
                ->firstOrFail();

            // A concurrent callback may have finalized this payment after the preflight check.
            if ($payment->status !== PaymentStatus::Pending) {
                return $payment;
            }

            if ($verificationResult === GatewayVerificationResult::PENDING_OR_INVALID) {
                return $payment;
            }

            $subscription = Subscription::query()
                ->whereKey($payment->subscription_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($verificationResult === GatewayVerificationResult::FAILED) {
                $payment->update([
                    'status' => PaymentStatus::Failed,
                ]);

                $subscription->events()->create([
                    'event_type' => SubscriptionEventType::PaymentRejected,
                    'metadata' => [
                        'payment_id' => $payment->getKey(),
                        'amount' => $payment->amount,
                        'gateway_reference' => $gatewayReference,
                        'reason' => 'gateway_confirmed_failure',
                    ],
                ]);

                return $payment->refresh();
            }

            $targetPlan = $payment->targetPlan()
                ->with('features')
                ->firstOrFail();

            /** @var array<string, mixed> $featuresSnapshot */
            $featuresSnapshot = $targetPlan->features
                ->mapWithKeys(
                    fn (PlanFeature $feature): array => [$feature->feature_key => $feature->value],
                )
                ->all();

            $previousPlan = $subscription->plan()->firstOrFail();
            $previousPlanId = $previousPlan->getKey();
            $now = now();
            $periodCandidates = [
                $now,
                $subscription->trial_ends_at,
                $subscription->current_period_ends_at,
            ];

            $periodBase = $now;

            foreach ($periodCandidates as $candidate) {
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
            $accessPeriodStartsAt = $preserveCurrentAccessPeriod
                ? $subscription->current_period_starts_at
                : $periodBase;
            $newPeriodEndsAt = $periodBase->copy()->addMonth();

            $payment->update([
                'status' => PaymentStatus::Successful,
                'paid_at' => $now,
            ]);

            $subscription->update([
                'plan_id' => $targetPlan->getKey(),
                'features_snapshot' => $featuresSnapshot,
                'status' => SubscriptionStatus::Active,
                'current_period_starts_at' => $accessPeriodStartsAt,
                'current_period_ends_at' => $newPeriodEndsAt,
            ]);

            $subscription->events()->create([
                'event_type' => SubscriptionEventType::PaymentApproved,
                'metadata' => [
                    'payment_id' => $payment->getKey(),
                    'amount' => $payment->amount,
                    'gateway_reference' => $gatewayReference,
                    'target_plan_id' => $targetPlan->getKey(),
                    'paid_at' => $now->toISOString(),
                    'current_period_starts_at' => $accessPeriodStartsAt->toISOString(),
                    'paid_period_starts_at' => $periodBase->toISOString(),
                    'current_period_ends_at' => $newPeriodEndsAt->toISOString(),
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
                    'payment_id' => $payment->getKey(),
                    'previous_plan_id' => $previousPlanId,
                    'new_plan_id' => $targetPlan->getKey(),
                ],
            ]);

            return $payment->refresh();
        }, attempts: 3);
    }
}
