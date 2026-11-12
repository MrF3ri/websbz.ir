<?php

namespace Tests\Feature\Subscription;

use App\Modules\Business\Models\Business;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Subscription\Models\Subscription;
use App\Modules\Subscription\Services\EntitlementService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class EntitlementServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
    }

    public function test_economic_plan_disables_custom_domain_and_limits_products_to_twenty(): void
    {
        $business = $this->createBusiness();
        $this->attachSubscription($business, 'economic');

        $entitlements = new EntitlementService($business);

        $this->assertFalse($entitlements->hasFeature('custom_domain'));
        $this->assertSame(20, $entitlements->getLimit('max_products'));
    }

    public function test_top_plan_enables_custom_domain(): void
    {
        $business = $this->createBusiness();
        $this->attachSubscription($business, 'top');

        $this->assertTrue((new EntitlementService($business))->hasFeature('custom_domain'));
    }

    public function test_business_without_a_subscription_has_no_entitlements(): void
    {
        $business = $this->createBusiness();
        $entitlements = new EntitlementService($business);

        $this->assertFalse($entitlements->hasFeature('custom_domain'));
        $this->assertNull($entitlements->getLimit('max_products'));
    }

    public function test_expired_subscription_does_not_grant_entitlements(): void
    {
        $business = $this->createBusiness();
        $this->attachSubscription(
            $business,
            'top',
            periodEndsAt: now()->subSecond(),
        );

        $this->assertFalse((new EntitlementService($business))->hasFeature('custom_domain'));
    }

    public function test_subscription_that_has_not_started_does_not_grant_entitlements(): void
    {
        $business = $this->createBusiness();
        $this->attachSubscription(
            $business,
            'top',
            periodStartsAt: now()->addSecond(),
        );

        $this->assertFalse((new EntitlementService($business))->hasFeature('custom_domain'));
    }

    public function test_past_due_subscription_does_not_grant_entitlements(): void
    {
        $business = $this->createBusiness();
        $this->attachSubscription($business, 'top', SubscriptionStatus::PastDue);

        $this->assertFalse((new EntitlementService($business))->hasFeature('custom_domain'));
    }

    public function test_canceled_subscription_does_not_grant_entitlements(): void
    {
        $business = $this->createBusiness();
        $this->attachSubscription($business, 'top', SubscriptionStatus::Canceled);

        $this->assertFalse((new EntitlementService($business))->hasFeature('custom_domain'));
    }

    public function test_inactive_plan_does_not_grant_entitlements(): void
    {
        $business = $this->createBusiness();
        $subscription = $this->attachSubscription($business, 'top');
        $subscription->plan->update(['is_active' => false]);

        $this->assertFalse((new EntitlementService($business))->hasFeature('custom_domain'));
    }

    public function test_trialing_subscription_grants_access_before_trial_expiration(): void
    {
        $business = $this->createBusiness();
        $this->attachSubscription(
            $business,
            'top',
            SubscriptionStatus::Trialing,
            trialEndsAt: now()->addDay(),
        );

        $this->assertTrue((new EntitlementService($business))->hasFeature('custom_domain'));
    }

    public function test_expired_trial_does_not_grant_entitlements(): void
    {
        $business = $this->createBusiness();
        $this->attachSubscription(
            $business,
            'top',
            SubscriptionStatus::Trialing,
            trialEndsAt: now()->subSecond(),
        );

        $this->assertFalse((new EntitlementService($business))->hasFeature('custom_domain'));
    }

    public function test_minus_one_plan_limit_is_returned_as_unlimited_null(): void
    {
        $business = $this->createBusiness();
        $this->attachSubscription($business, 'professional');

        $this->assertNull((new EntitlementService($business))->getLimit('max_products'));
    }

    private function createBusiness(): Business
    {
        return Business::query()->create([
            'name' => 'Entitlement Test Business',
            'slug' => 'entitlement-test-'.Str::lower(Str::random(10)),
        ]);
    }

    private function attachSubscription(
        Business $business,
        string $planCode,
        SubscriptionStatus $status = SubscriptionStatus::Active,
        ?Carbon $periodStartsAt = null,
        ?Carbon $periodEndsAt = null,
        ?Carbon $trialEndsAt = null,
    ): Subscription {
        $plan = Plan::query()
            ->where('code', $planCode)
            ->firstOrFail();

        return Subscription::query()->create([
            'business_id' => $business->getKey(),
            'plan_id' => $plan->getKey(),
            'status' => $status,
            'trial_ends_at' => $trialEndsAt,
            'current_period_starts_at' => $periodStartsAt ?? now()->subDay(),
            'current_period_ends_at' => $periodEndsAt ?? now()->addMonth(),
        ]);
    }
}
