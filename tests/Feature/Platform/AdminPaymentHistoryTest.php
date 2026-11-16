<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Modules\Subscription\Enums\PaymentStatus;
use App\Modules\Subscription\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithBusinesses;
use Tests\TestCase;

class AdminPaymentHistoryTest extends TestCase
{
    use InteractsWithBusinesses;
    use RefreshDatabase;

    public function test_platform_admin_can_filter_payment_history_by_status_and_search_term(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_platform_admin' => true])->save();

        $business = $this->createBusinessFor($admin, attributes: [
            'name' => 'Payment History Cafe',
            'slug' => 'payment-history-cafe',
        ]);
        $subscription = $this->giveBusinessTrialSubscription($business, 'economic');
        $plan = Plan::query()->where('code', 'economic')->firstOrFail();

        foreach ([
            [PaymentStatus::PendingReview, 'PAY-HIST-PENDING-001'],
            [PaymentStatus::Approved, 'PAY-HIST-APPROVED-002'],
            [PaymentStatus::Rejected, 'PAY-HIST-REJECTED-003'],
        ] as [$status, $tracking]) {
            $subscription->payments()->create([
                'target_plan_id' => $plan->getKey(),
                'amount' => $plan->monthly_price,
                'status' => $status,
                'bank_tracking_number' => $tracking,
                'submitted_at' => now(),
                'paid_at' => $status === PaymentStatus::Approved ? now() : null,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.payments.index', [
                'status' => PaymentStatus::Approved->value,
                'search' => 'PAY-HIST-APPROVED',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Payments/Index')
                ->where('filters.status', PaymentStatus::Approved->value)
                ->where('filters.search', 'PAY-HIST-APPROVED')
                ->where('payments.total', 1)
                ->has('payments.data', 1)
                ->where('payments.data.0.status', PaymentStatus::Approved->value)
                ->where('payments.data.0.bank_tracking_number', 'PAY-HIST-APPROVED-002')
                ->where('payments.data.0.receipt_url', null)
                ->has('statuses', 6));
    }

    public function test_payment_queue_defaults_to_pending_review(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_platform_admin' => true])->save();

        $business = $this->createBusinessFor($admin);
        $subscription = $this->giveBusinessTrialSubscription($business, 'economic');
        $plan = Plan::query()->where('code', 'economic')->firstOrFail();

        foreach ([PaymentStatus::PendingReview, PaymentStatus::Approved] as $index => $status) {
            $subscription->payments()->create([
                'target_plan_id' => $plan->getKey(),
                'amount' => $plan->monthly_price,
                'status' => $status,
                'bank_tracking_number' => 'PAY-QUEUE-'.$index,
                'submitted_at' => now(),
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.payments.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', PaymentStatus::PendingReview->value)
                ->where('payments.total', 1)
                ->where('payments.data.0.status', PaymentStatus::PendingReview->value));
    }
}
