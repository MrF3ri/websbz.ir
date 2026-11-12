<?php

namespace Tests\Feature\Subscription;

use App\Modules\Business\Models\Business;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Subscription\Models\Subscription;
use App\Modules\Subscription\Services\EntitlementService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EntitlementServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_economic_plan_disables_custom_domain_and_limits_products_to_twenty(): void
    {
        $this->seed(PlanSeeder::class);

        $business = $this->createBusinessWithPlan('economic');
        $entitlements = new EntitlementService($business);

        $this->assertFalse($entitlements->hasFeature('custom_domain'));
        $this->assertSame(20, $entitlements->getLimit('max_products'));
    }

    public function test_top_plan_enables_custom_domain(): void
    {
        $this->seed(PlanSeeder::class);

        $business = $this->createBusinessWithPlan('top');
        $entitlements = new EntitlementService($business);

        $this->assertTrue($entitlements->hasFeature('custom_domain'));
    }

    private function createBusinessWithPlan(string $planCode): Business
    {
        $business = Business::query()->create([
            'name' => 'Entitlement Test Business',
            'slug' => 'entitlement-test-'.Str::lower(Str::random(10)),
        ]);

        $plan = Plan::query()
            ->where('code', $planCode)
            ->firstOrFail();

        Subscription::query()->create([
            'business_id' => $business->getKey(),
            'plan_id' => $plan->getKey(),
            'status' => SubscriptionStatus::Active,
            'current_period_starts_at' => now()->subDay(),
            'current_period_ends_at' => now()->addMonth(),
        ]);

        return $business;
    }
}
