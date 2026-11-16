<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Modules\Business\Models\Business;
use App\Modules\Subscription\Enums\PaymentStatus;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use App\Modules\Subscription\Models\Payment;
use App\Modules\Subscription\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController
{
    public function index(): Response
    {
        $metrics = [
            'businesses' => Business::query()->count(),
            'active_subscriptions' => Subscription::query()
                ->whereIn('status', [
                    SubscriptionStatus::Trialing->value,
                    SubscriptionStatus::Active->value,
                ])
                ->count(),
            'pending_payments' => Payment::query()
                ->where('status', PaymentStatus::PendingReview->value)
                ->count(),
            'approved_payments_total' => (int) Payment::query()
                ->where('status', PaymentStatus::Approved->value)
                ->sum('amount'),
            'approved_payments_this_month' => (int) Payment::query()
                ->where('status', PaymentStatus::Approved->value)
                ->whereNotNull('paid_at')
                ->where('paid_at', '>=', now()->startOfMonth())
                ->sum('amount'),
        ];

        $recentPayments = Payment::query()
            ->with(['subscription.business', 'targetPlan'])
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (Payment $payment): array => [
                'id' => $payment->getKey(),
                'business_name' => $payment->subscription->business->name,
                'plan_name' => $payment->targetPlan->name,
                'amount' => $payment->amount,
                'status' => $payment->status->value,
                'created_at' => $payment->created_at?->toISOString(),
            ])
            ->values();

        return Inertia::render('Admin/Dashboard/Index', [
            'metrics' => $metrics,
            'recentPayments' => $recentPayments,
        ]);
    }
}
