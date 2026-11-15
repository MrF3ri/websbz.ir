<?php

namespace App\Modules\Subscription\Http\Controllers;

use App\Modules\Platform\Services\PlatformSettingsService;
use App\Modules\Subscription\Models\Payment;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Tenancy\Services\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BillingController
{
    public function index(
        CurrentBusiness $currentBusiness,
        PlatformSettingsService $platformSettings,
    ): Response|RedirectResponse {
        $business = $currentBusiness->get();
        abort_unless($business !== null, 404);

        $subscription = $business->subscriptions()
            ->with(['plan', 'payments.targetPlan'])
            ->latest('id')
            ->first();

        if ($subscription === null) {
            return redirect()->route('onboarding');
        }

        $plans = Plan::query()
            ->where('is_active', true)
            ->orderBy('monthly_price')
            ->get(['id', 'code', 'name', 'monthly_price']);

        return Inertia::render('Billing/Index', [
            'subscription' => [
                'status' => $subscription->status->value,
                'plan' => [
                    'id' => $subscription->plan->getKey(),
                    'name' => $subscription->plan->name,
                    'monthly_price' => $subscription->plan->monthly_price,
                ],
                'trial_ends_at' => $subscription->trial_ends_at?->toISOString(),
                'current_period_ends_at' => $subscription->current_period_ends_at?->toISOString(),
            ],
            'plans' => $plans->map(fn (Plan $plan): array => [
                'id' => $plan->getKey(),
                'code' => $plan->code,
                'name' => $plan->name,
                'monthly_price' => $plan->monthly_price,
            ])->values(),
            'cardDetails' => $platformSettings->cardDetails(),
            'hasCardDetails' => $platformSettings->hasCardDetails(),
            'payments' => $subscription->payments()
                ->with('targetPlan')
                ->latest('id')
                ->limit(10)
                ->get()
                ->map(fn (Payment $payment): array => [
                    'id' => $payment->getKey(),
                    'amount' => $payment->amount,
                    'status' => $payment->status->value,
                    'plan_name' => $payment->targetPlan->name,
                    'bank_tracking_number' => $payment->bank_tracking_number,
                    'submitted_at' => $payment->submitted_at?->toISOString(),
                    'rejection_reason' => $payment->rejection_reason,
                ])
                ->values(),
        ]);
    }
}
