<?php

namespace App\Modules\Subscription\Http\Controllers;

use App\Modules\Platform\Services\PlatformSettingsService;
use App\Modules\Subscription\Enums\PaymentStatus;
use App\Modules\Subscription\Models\Payment;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Tenancy\Services\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
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
        Gate::authorize('update', $business);

        $subscription = $business->subscriptions()
            ->with('plan')
            ->withExists(['payments as has_pending_review' => fn ($query) => $query->where('status', PaymentStatus::PendingReview->value)])
            ->latest('id')
            ->first();

        if ($subscription === null) {
            return redirect()->route('onboarding');
        }

        $payments = $subscription->payments()
            ->with('targetPlan')
            ->latest('id')
            ->limit(10)
            ->get();

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
            'hasPendingReview' => (bool) $subscription->getAttribute('has_pending_review'),
            'payments' => $payments
                ->map(fn (Payment $payment): array => [
                    'id' => $payment->getKey(),
                    'amount' => $payment->amount,
                    'status' => $payment->status->value,
                    'plan_name' => $payment->targetPlan->name,
                    'bank_tracking_number' => $payment->bank_tracking_number,
                    'submitted_at' => $payment->submitted_at?->toISOString(),
                    'rejection_reason' => $payment->rejection_reason,
                    'receipt_url' => is_string($payment->receipt_path) && $payment->receipt_path !== ''
                        ? route('billing.payments.receipt', $payment)
                        : null,
                ])
                ->values(),
        ]);
    }
}
