<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Modules\Subscription\Enums\PaymentStatus;
use App\Modules\Subscription\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithBusinesses;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use InteractsWithBusinesses;
    use RefreshDatabase;

    public function test_platform_admin_sees_live_platform_metrics_and_recent_payments(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_platform_admin' => true])->save();

        $business = $this->createBusinessFor($admin);
        $subscription = $this->giveBusinessTrialSubscription($business, 'economic');
        $plan = Plan::query()->where('code', 'economic')->firstOrFail();

        $subscription->payments()->create([
            'target_plan_id' => $plan->getKey(),
            'amount' => 350000,
            'status' => PaymentStatus::PendingReview,
            'bank_tracking_number' => 'ADMIN-DASH-PENDING-001',
            'submitted_at' => now(),
        ]);

        $subscription->payments()->create([
            'target_plan_id' => $plan->getKey(),
            'amount' => 350000,
            'status' => PaymentStatus::Approved,
            'bank_tracking_number' => 'ADMIN-DASH-APPROVED-001',
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now(),
            'paid_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard/Index')
                ->where('metrics.businesses', 1)
                ->where('metrics.active_subscriptions', 1)
                ->where('metrics.pending_payments', 1)
                ->where('metrics.approved_payments_total', 350000)
                ->where('metrics.approved_payments_this_month', 350000)
                ->has('recentPayments', 2)
                ->where('recentPayments.0.status', PaymentStatus::Approved->value)
                ->where('recentPayments.1.status', PaymentStatus::PendingReview->value));
    }

    public function test_non_platform_admin_cannot_open_platform_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }
}
