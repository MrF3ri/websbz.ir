<?php

namespace Tests\Feature\Performance;

use App\Models\User;
use App\Modules\Platform\Services\PlatformSettingsService;
use App\Modules\Subscription\Enums\PaymentStatus;
use App\Modules\Subscription\Models\Plan;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithBusinesses;
use Tests\TestCase;

class AdminDashboardQueryBudgetTest extends TestCase
{
    use InteractsWithBusinesses;
    use RefreshDatabase;

    public function test_dashboard_select_query_count_does_not_grow_with_payment_volume(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_platform_admin' => true])->save();

        $business = $this->createBusinessFor($admin);
        $subscription = $this->giveBusinessTrialSubscription($business, 'economic');
        $plan = Plan::query()->where('code', 'economic')->firstOrFail();

        app(PlatformSettingsService::class)->updateCardDetails([
            'bank_name' => 'ملت',
            'card_number' => '1234567812345678',
            'card_holder' => 'Platform Owner',
        ], $admin);

        $subscription->payments()->create([
            'target_plan_id' => $plan->getKey(),
            'amount' => $plan->monthly_price,
            'status' => PaymentStatus::PendingReview,
            'bank_tracking_number' => 'DASHBOARD-QUERY-BASELINE',
            'submitted_at' => now(),
        ]);

        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            if (str_starts_with(strtolower(ltrim($query->sql)), 'select')) {
                $queries[] = $query->sql;
            }
        });

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk();

        $baselineQueryCount = count($queries);

        for ($payment = 1; $payment <= 30; $payment++) {
            $subscription->payments()->create([
                'target_plan_id' => $plan->getKey(),
                'amount' => $plan->monthly_price,
                'status' => PaymentStatus::Approved,
                'bank_tracking_number' => 'DASHBOARD-QUERY-'.$payment,
                'submitted_at' => now()->subDays($payment),
                'reviewed_at' => now(),
                'paid_at' => now()->subDays($payment),
            ]);
        }

        $queries = [];

        $this->get(route('admin.dashboard'))
            ->assertOk();

        $highVolumeQueryCount = count($queries);

        $this->assertGreaterThan(0, $baselineQueryCount);
        $this->assertLessThanOrEqual(
            $baselineQueryCount + 1,
            $highVolumeQueryCount,
            'Admin dashboard SELECT query count must remain bounded as payment volume grows.',
        );
    }
}
