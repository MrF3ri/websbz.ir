<?php

namespace App\Modules\Subscription\Actions;

use App\Modules\Subscription\Enums\PaymentStatus;
use App\Modules\Subscription\Enums\SubscriptionEventType;
use App\Modules\Subscription\Models\Payment;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Subscription\Models\PlanFeature;
use App\Modules\Subscription\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitManualPaymentAction
{
    public function execute(
        Subscription $subscription,
        Plan $plan,
        string $bankTrackingNumber,
        string $receiptPath,
    ): Payment {
        return DB::transaction(function () use (
            $subscription,
            $plan,
            $bankTrackingNumber,
            $receiptPath,
        ): Payment {
            $lockedSubscription = Subscription::query()
                ->whereKey($subscription->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedPlan = Plan::query()
                ->with('features')
                ->whereKey($plan->getKey())
                ->where('is_active', true)
                ->firstOrFail();

            $hasPendingReview = $lockedSubscription->payments()
                ->where('status', PaymentStatus::PendingReview)
                ->lockForUpdate()
                ->first() !== null;

            if ($hasPendingReview) {
                throw ValidationException::withMessages([
                    'payment' => 'یک پرداخت شما در انتظار بررسی است. تا تعیین نتیجه، پرداخت جدید ثبت نکنید.',
                ]);
            }

            /** @var array<string, mixed> $featuresSnapshot */
            $featuresSnapshot = $lockedPlan->features
                ->mapWithKeys(
                    fn (PlanFeature $feature): array => [$feature->feature_key => $feature->value],
                )
                ->all();

            $payment = $lockedSubscription->payments()->create([
                'target_plan_id' => $lockedPlan->getKey(),
                'target_features_snapshot' => $featuresSnapshot,
                'amount' => $lockedPlan->monthly_price,
                'status' => PaymentStatus::PendingReview,
                'bank_tracking_number' => $bankTrackingNumber,
                'receipt_path' => $receiptPath,
                'submitted_at' => now(),
            ]);

            $lockedSubscription->events()->create([
                'event_type' => SubscriptionEventType::PaymentSubmitted,
                'metadata' => [
                    'payment_id' => $payment->getKey(),
                    'amount' => $payment->amount,
                    'target_plan_id' => $lockedPlan->getKey(),
                    'bank_tracking_number' => $bankTrackingNumber,
                ],
            ]);

            return $payment;
        }, attempts: 3);
    }
}
