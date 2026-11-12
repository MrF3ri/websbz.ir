<?php

namespace Tests\Feature\Subscription;

use App\Modules\Business\Models\Business;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Subscription\Models\PlanFeature;
use App\Modules\Subscription\Models\Subscription;
use App\Modules\Subscription\Services\EntitlementService;
use Carbon\CarbonImmutable;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_economic_snapshot_disables_custom_domain_and_limits_products_to_twenty(): void
    {
        $business = $this->createBusiness();
        $this->attachSubscription($business, 'economic');

        $entitlements = new EntitlementService($business);

        $this->assertFalse($entitlements->hasFeature('custom_domain'));
        $this->assertSame(20, $entitlements->getLimit('max_products'));
    }

    public function test_top_snapshot_enables_custom_domain(): void
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

    public function test_subscription_creation_captures_a_versioned_plan_snapshot(): void
    {
        $business = $this->createBusiness();
        $subscription = $this->attachSubscription($business, 'economic');
        $subscription->refresh();
        $snapshot = $subscription->features_snapshot ?? [];

        $this->assertSame(20, $snapshot['max_products'] ?? null);
        $this->assertNotEmpty($subscription->plan_version);
    }

    public function test_plan_change_replaces_the_snapshot_and_its_version(): void
    {
        $business = $this->createBusiness();
        $subscription = $this->attachSubscription($business, 'economic');
        $originalVersion = $subscription->plan_version;
        $topPlan = Plan::query()->where('code', 'top')->firstOrFail();

        $subscription->update(['plan_id' => $topPlan->getKey()]);
        $subscription->refresh();

        $snapshot = $subscription->features_snapshot ?? [];

        $this->assertTrue($snapshot['custom_domain'] ?? false);
        $this->assertSame(-1, $snapshot['max_categories'] ?? null);
        $this->assertNotSame($originalVersion, $subscription->plan_version);
    }

    public function test_undefined_snapshot_feature_returns_null_limit_and_false_boolean(): void
    {
        $business = $this->createBusiness();
        $this->attachSubscription(
            $business,
            'economic',
            snapshot: [
                'max_products' => 20,
                'explicitly_undefined' => null,
            ],
        );

        $entitlements = new EntitlementService($business);

        $this->assertNull($entitlements->getLimit('not_configured'));
        $this->assertNull($entitlements->getLimit('explicitly_undefined'));
        $this->assertFalse($entitlements->hasFeature('not_configured'));
    }

    public function test_minus_one_snapshot_limit_is_returned_as_unlimited(): void
    {
        $business = $this->createBusiness();
        $this->attachSubscription(
            $business,
            'professional',
            snapshot: ['max_products' => -1],
        );

        $this->assertSame(-1, (new EntitlementService($business))->getLimit('max_products'));
    }

    public function test_existing_subscription_uses_snapshot_after_live_plan_features_change(): void
    {
        $business = $this->createBusiness();
        $subscription = $this->attachSubscription($business, 'economic');

        PlanFeature::query()
            ->where('plan_id', $subscription->plan_id)
            ->where('feature_key', 'max_products')
            ->update(['value' => json_encode(999, JSON_THROW_ON_ERROR)]);

        $entitlements = new EntitlementService($business);

        $this->assertSame(20, $entitlements->getLimit('max_products'));
    }

    public function test_expired_subscription_does_not_grant_entitlements(): void
    {
        $business = $this->createBusiness();
        $this->attachSubscription($business, 'top', periodEndsAt: CarbonImmutable::now()->subSecond());

        $this->assertFalse((new EntitlementService($business))->hasFeature('custom_domain'));
    }

    public function test_subscription_that_has_not_started_does_not_grant_entitlements(): void
    {
        $business = $this->createBusiness();
        $this->attachSubscription($business, 'top', periodStartsAt: CarbonImmutable::now()->addSecond());

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

    public function test_expired_status_does_not_grant_entitlements(): void
    {
        $business = $this->createBusiness();
        $this->attachSubscription($business, 'top', SubscriptionStatus::Expired);

        $this->assertFalse((new EntitlementService($business))->hasFeature('custom_domain'));
    }

    public function test_trialing_subscription_without_trial_end_does_not_grant_entitlements(): void
    {
        $business = $this->createBusiness();
        $this->attachSubscription($business, 'top', SubscriptionStatus::Trialing);

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
            trialEndsAt: CarbonImmutable::now()->addDay(),
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
            trialEndsAt: CarbonImmutable::now()->subSecond(),
        );

        $this->assertFalse((new EntitlementService($business))->hasFeature('custom_domain'));
    }

    private function createBusiness(): Business
    {
        return Business::query()->create([
            'name' => 'Entitlement Test Business',
            'slug' => 'entitlement-test-'.Str::lower(Str::random(10)),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $snapshot
     */
    private function attachSubscription(
        Business $business,
        string $planCode,
        SubscriptionStatus $status = SubscriptionStatus::Active,
        ?CarbonImmutable $periodStartsAt = null,
        ?CarbonImmutable $periodEndsAt = null,
        ?CarbonImmutable $trialEndsAt = null,
        ?array $snapshot = null,
    ): Subscription {
        $plan = Plan::query()
            ->where('code', $planCode)
            ->firstOrFail();

        return Subscription::query()->create([
            'business_id' => $business->getKey(),
            'plan_id' => $plan->getKey(),
            'status' => $status,
            'features_snapshot' => $snapshot,
            'trial_ends_at' => $trialEndsAt,
            'current_period_starts_at' => $periodStartsAt ?? CarbonImmutable::now()->subDay(),
            'current_period_ends_at' => $periodEndsAt ?? CarbonImmutable::now()->addMonth(),
        ]);
    }
}
