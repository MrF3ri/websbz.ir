<?php

namespace Tests\Feature\Subscription;

use App\Models\User;
use App\Modules\Subscription\Enums\PaymentStatus;
use App\Modules\Subscription\Models\Plan;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithBusinesses;
use Tests\TestCase;

class BillingPageQueryBudgetTest extends TestCase
{
    use InteractsWithBusinesses;
    use RefreshDatabase;

    public function test_billing_page_queries_recent_payments_once_and_checks_pending_status_in_subscription_query(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = $this->createBusinessFor($user);
        $subscription = $this->giveBusinessTrialSubscription($business, 'economic');
        $targetPlan = Plan::query()->where('code', 'professional')->firstOrFail();

        for ($index = 1; $index <= 12; $index++) {
            $subscription->payments()->create([
                'target_plan_id' => $targetPlan->getKey(),
                'amount' => $targetPlan->monthly_price,
                'status' => $index === 12 ? PaymentStatus::PendingReview : PaymentStatus::Approved,
                'bank_tracking_number' => 'BILLING-QUERY-'.$index,
                'submitted_at' => now()->subMinutes($index),
            ]);
        }

        $paymentQueries = [];

        DB::listen(function (QueryExecuted $query) use (&$paymentQueries): void {
            if (str_contains(strtolower($query->sql), 'payments')) {
                $paymentQueries[] = strtolower($query->sql);
            }
        });

        $this->actingAs($user)
            ->get(route('billing.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('hasPendingReview', true)
                ->has('payments', 10)
                ->where('payments.0.bank_tracking_number', 'BILLING-QUERY-12'));

        // One correlated EXISTS in the subscription query and one bounded query
        // for the latest ten payments; no unbounded eager load or second history read.
        $this->assertCount(2, $paymentQueries);
        $this->assertStringContainsString('exists', $paymentQueries[0]);
        $this->assertStringContainsString('limit 10', $paymentQueries[1]);
    }
}
