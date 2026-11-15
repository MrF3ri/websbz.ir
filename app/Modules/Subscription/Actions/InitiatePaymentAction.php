<?php

namespace App\Modules\Subscription\Actions;

use App\Modules\Subscription\Contracts\PaymentGatewayInterface;
use App\Modules\Subscription\Enums\PaymentStatus;
use App\Modules\Subscription\Enums\SubscriptionEventType;
use App\Modules\Subscription\Models\Payment;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Subscription\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class InitiatePaymentAction
{
    public function __construct(
        private readonly PaymentGatewayInterface $paymentGateway,
    ) {}

    /**
     * Create a pending payment using the persisted target plan price and return the provider URL.
     */
    public function execute(Subscription $subscription, Plan $plan): string
    {
        $payment = DB::transaction(function () use ($subscription, $plan): Payment {
            $lockedSubscription = Subscription::query()
                ->whereKey($subscription->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedPlan = Plan::query()
                ->whereKey($plan->getKey())
                ->firstOrFail();

            if ($lockedPlan->monthly_price <= 0) {
                throw new InvalidArgumentException('The target plan price must be greater than zero.');
            }

            $payment = $lockedSubscription->payments()->create([
                'target_plan_id' => $lockedPlan->getKey(),
                'amount' => $lockedPlan->monthly_price,
                'status' => PaymentStatus::Pending,
                'gateway_reference' => (string) Str::uuid(),
            ]);

            $lockedSubscription->events()->create([
                'event_type' => SubscriptionEventType::PaymentSubmitted,
                'metadata' => [
                    'payment_id' => $payment->getKey(),
                    'amount' => $payment->amount,
                    'target_plan_id' => $lockedPlan->getKey(),
                    'gateway_reference' => $payment->gateway_reference,
                ],
            ]);

            return $payment;
        }, attempts: 3);

        // Never hold database locks while calling the external payment provider.
        return $this->paymentGateway->initiatePayment($payment);
    }
}
