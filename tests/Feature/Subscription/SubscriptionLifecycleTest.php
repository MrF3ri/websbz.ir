<?php

namespace Tests\Feature\Subscription;

use App\Modules\Business\Models\Business;
use App\Modules\Subscription\Actions\ExpireSubscriptionsAction;
use App\Modules\Subscription\Actions\InitiatePaymentAction;
use App\Modules\Subscription\Actions\ProcessPaymentCallbackAction;
use App\Modules\Subscription\Actions\StartTrialAction;
use App\Modules\Subscription\Contracts\PaymentGatewayInterface;
use App\Modules\Subscription\Enums\GatewayVerificationResult;
use App\Modules\Subscription\Enums\PaymentStatus;
use App\Modules\Subscription\Enums\SubscriptionEventType;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use App\Modules\Subscription\Models\Payment;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Subscription\Models\PlanFeature;
use App\Modules\Subscription\Services\EntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

class SubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_start_trial_creates_a_72_hour_trial_with_the_plan_feature_snapshot(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-12 10:00:00', 'UTC'));

        $business = $this->createBusiness();
        $plan = $this->createPlan('starter', 1000, [
            'menu_limit' => 10,
            'analytics' => true,
        ]);

        $subscription = app(StartTrialAction::class)->execute($business, $plan);

        $this->assertSame(SubscriptionStatus::Trialing, $subscription->status);
        $this->assertSame(
            Carbon::now()->addHours(72)->timestamp,
            $subscription->trial_ends_at?->timestamp,
        );
        $this->assertEqualsCanonicalizing(
            [
                'menu_limit' => 10,
                'analytics' => true,
            ],
            $subscription->features_snapshot,
        );
        $this->assertSame(
            Carbon::now()->addHours(72)->timestamp,
            $subscription->current_period_ends_at?->timestamp,
        );
        $this->assertDatabaseHas('subscription_events', [
            'subscription_id' => $subscription->getKey(),
            'event_type' => SubscriptionEventType::TrialStarted->value,
        ]);
    }

    public function test_expired_trial_is_persisted_once_with_trial_ended_and_expired_events(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-12 10:00:00', 'UTC'));

        $business = $this->createBusiness();
        $plan = $this->createPlan('starter', 1000, ['menu_limit' => 10]);
        $subscription = app(StartTrialAction::class)->execute($business, $plan);
        $trialEndsAt = $subscription->trial_ends_at;

        self::assertNotNull($trialEndsAt);
        Carbon::setTestNow($trialEndsAt->copy()->addSecond());

        $action = app(ExpireSubscriptionsAction::class);

        $this->assertSame(1, $action->execute());
        $this->assertSame(SubscriptionStatus::Expired, $subscription->fresh()->status);
        $this->assertSame(
            1,
            $subscription->events()->where('event_type', SubscriptionEventType::TrialEnded->value)->count(),
        );
        $this->assertSame(
            1,
            $subscription->events()->where('event_type', SubscriptionEventType::Expired->value)->count(),
        );
        $this->assertTrue($business->exists);
        $this->assertSame(0, $action->execute());
        $this->assertSame(
            1,
            $subscription->events()->where('event_type', SubscriptionEventType::Expired->value)->count(),
        );
    }

    public function test_expired_paid_subscription_transitions_without_trial_event_and_future_trial_is_untouched(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-12 10:00:00', 'UTC'));

        $expiredBusiness = $this->createBusiness();
        $plan = $this->createPlan('starter', 1000, ['menu_limit' => 10]);
        $expiredSubscription = app(StartTrialAction::class)->execute($expiredBusiness, $plan);
        $expiredSubscription->update([
            'status' => SubscriptionStatus::Active,
            'current_period_starts_at' => Carbon::now()->subMonth(),
            'current_period_ends_at' => Carbon::now()->subSecond(),
        ]);

        $futureBusiness = Business::query()->create([
            'name' => 'Future Trial Business',
            'slug' => 'future-trial-'.uniqid(),
        ]);
        $futureSubscription = app(StartTrialAction::class)->execute($futureBusiness, $plan);

        $this->assertSame(1, app(ExpireSubscriptionsAction::class)->execute());

        $this->assertSame(SubscriptionStatus::Expired, $expiredSubscription->fresh()->status);
        $this->assertSame(
            0,
            $expiredSubscription->events()->where('event_type', SubscriptionEventType::TrialEnded->value)->count(),
        );
        $this->assertSame(
            1,
            $expiredSubscription->events()->where('event_type', SubscriptionEventType::Expired->value)->count(),
        );
        $this->assertSame(SubscriptionStatus::Trialing, $futureSubscription->fresh()->status);
    }

    public function test_initiate_payment_persists_target_plan_and_pending_status(): void
    {
        $business = $this->createBusiness();
        $currentPlan = $this->createPlan('starter', 1000, ['menu_limit' => 10]);
        $targetPlan = $this->createPlan('pro', 5000, ['menu_limit' => 100]);
        $subscription = app(StartTrialAction::class)->execute($business, $currentPlan);

        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('initiatePayment')
            ->once()
            ->with(Mockery::on(
                fn (Payment $payment): bool => $payment->target_plan_id === $targetPlan->getKey()
                    && $payment->status === PaymentStatus::Pending
                    && $payment->amount === $targetPlan->monthly_price,
            ))
            ->andReturn('https://gateway.test/checkout');

        $this->app->instance(PaymentGatewayInterface::class, $gateway);

        $checkoutUrl = app(InitiatePaymentAction::class)->execute($subscription, $targetPlan);
        $payment = Payment::query()->sole();

        $this->assertSame('https://gateway.test/checkout', $checkoutUrl);
        $this->assertSame($targetPlan->getKey(), $payment->target_plan_id);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame($targetPlan->getKey(), $payment->targetPlan->getKey());
    }

    public function test_callback_is_idempotent_for_a_payment_that_is_already_successful(): void
    {
        $business = $this->createBusiness();
        $plan = $this->createPlan('starter', 1000, ['menu_limit' => 10]);
        $subscription = app(StartTrialAction::class)->execute($business, $plan);

        $payment = $subscription->payments()->create([
            'target_plan_id' => $plan->getKey(),
            'amount' => $plan->monthly_price,
            'status' => PaymentStatus::Successful,
            'gateway_reference' => 'already-paid-reference',
            'paid_at' => now(),
        ]);

        $eventsBefore = $subscription->events()->count();

        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldNotReceive('verifyPayment');
        $this->app->instance(PaymentGatewayInterface::class, $gateway);

        $returnedPayment = app(ProcessPaymentCallbackAction::class)
            ->execute('already-paid-reference');

        $this->assertSame($payment->getKey(), $returnedPayment->getKey());
        $this->assertSame(PaymentStatus::Successful, $returnedPayment->status);
        $this->assertSame($eventsBefore, $subscription->events()->count());
        $this->assertSame(
            SubscriptionStatus::Trialing,
            $subscription->fresh()->status,
        );
    }

    public function test_successful_callback_upgrades_plan_snapshot_and_subscription_period(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-12 10:00:00', 'UTC'));

        $business = $this->createBusiness();
        $currentPlan = $this->createPlan('starter', 1000, [
            'menu_limit' => 10,
            'analytics' => false,
        ]);
        $targetPlan = $this->createPlan('pro', 5000, [
            'menu_limit' => 100,
            'analytics' => true,
        ]);

        $subscription = app(StartTrialAction::class)->execute($business, $currentPlan);
        $trialEnd = $subscription->trial_ends_at?->copy();

        $payment = $subscription->payments()->create([
            'target_plan_id' => $targetPlan->getKey(),
            'amount' => $targetPlan->monthly_price,
            'status' => PaymentStatus::Pending,
            'gateway_reference' => 'upgrade-success-reference',
        ]);

        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('verifyPayment')
            ->once()
            ->with('upgrade-success-reference')
            ->andReturn(GatewayVerificationResult::SUCCESS);
        $this->app->instance(PaymentGatewayInterface::class, $gateway);

        $processedPayment = app(ProcessPaymentCallbackAction::class)
            ->execute('upgrade-success-reference');

        $freshSubscription = $subscription->fresh();

        $this->assertSame(PaymentStatus::Successful, $processedPayment->status);
        $this->assertNotNull($processedPayment->paid_at);
        $this->assertSame($targetPlan->getKey(), $freshSubscription->plan_id);
        $this->assertEqualsCanonicalizing(
            [
                'menu_limit' => 100,
                'analytics' => true,
            ],
            $freshSubscription->features_snapshot,
        );
        $this->assertSame(SubscriptionStatus::Active, $freshSubscription->status);
        $this->assertSame(
            $trialEnd?->copy()->addMonth()->timestamp,
            $freshSubscription->current_period_ends_at?->timestamp,
        );

        $this->assertDatabaseHas('subscription_events', [
            'subscription_id' => $subscription->getKey(),
            'event_type' => SubscriptionEventType::PaymentApproved->value,
        ]);
        $this->assertDatabaseHas('subscription_events', [
            'subscription_id' => $subscription->getKey(),
            'event_type' => SubscriptionEventType::Upgraded->value,
        ]);
        $this->assertSame($targetPlan->getKey(), $payment->fresh()->target_plan_id);
    }

    public function test_early_successful_callback_preserves_trial_access_until_the_paid_period_starts(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-16 10:00:00', 'UTC'));

        $business = $this->createBusiness();
        $currentPlan = $this->createPlan('starter', 1000, ['max_products' => 10]);
        $targetPlan = $this->createPlan('pro', 5000, ['max_products' => 100]);
        $subscription = app(StartTrialAction::class)->execute($business, $currentPlan);
        $trialStartsAt = $subscription->current_period_starts_at;
        $trialEndsAt = $subscription->trial_ends_at;

        $payment = $subscription->payments()->create([
            'target_plan_id' => $targetPlan->getKey(),
            'amount' => $targetPlan->monthly_price,
            'status' => PaymentStatus::Pending,
            'gateway_reference' => 'early-success-reference',
        ]);

        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('verifyPayment')
            ->once()
            ->with('early-success-reference')
            ->andReturn(GatewayVerificationResult::SUCCESS);
        $this->app->instance(PaymentGatewayInterface::class, $gateway);

        app(ProcessPaymentCallbackAction::class)->execute('early-success-reference');

        $freshSubscription = $subscription->fresh();
        self::assertNotNull($trialStartsAt);
        self::assertNotNull($trialEndsAt);
        $this->assertSame($trialStartsAt->timestamp, $freshSubscription->current_period_starts_at?->timestamp);
        $this->assertSame(
            $trialEndsAt->copy()->addMonth()->timestamp,
            $freshSubscription->current_period_ends_at?->timestamp,
        );
        $this->assertSame(100, (new EntitlementService($business))->getLimit('max_products'));

        Carbon::setTestNow($trialEndsAt);
        $this->assertSame(100, (new EntitlementService($business))->getLimit('max_products'));
        $this->assertDatabaseHas('subscription_events', [
            'subscription_id' => $subscription->getKey(),
            'event_type' => SubscriptionEventType::PaymentApproved->value,
        ]);
        $this->assertDatabaseHas('subscription_events', [
            'subscription_id' => $subscription->getKey(),
            'event_type' => SubscriptionEventType::PaymentApproved->value,
            'metadata->paid_period_starts_at' => $trialEndsAt->toISOString(),
        ]);
    }

    public function test_delayed_successful_callback_after_trial_expiration_starts_a_fresh_paid_period_and_is_idempotent(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-12 10:00:00', 'UTC'));

        $business = $this->createBusiness();
        $plan = $this->createPlan('starter', 1000, ['menu_limit' => 10]);
        $subscription = app(StartTrialAction::class)->execute($business, $plan);
        $trialEndsAt = $subscription->trial_ends_at;
        $payment = $subscription->payments()->create([
            'target_plan_id' => $plan->getKey(),
            'amount' => $plan->monthly_price,
            'status' => PaymentStatus::Pending,
            'gateway_reference' => 'delayed-success-reference',
        ]);

        self::assertNotNull($trialEndsAt);
        Carbon::setTestNow($trialEndsAt->copy()->addDays(2));
        app(ExpireSubscriptionsAction::class)->execute();

        $paidAt = Carbon::now();
        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('verifyPayment')
            ->once()
            ->with('delayed-success-reference')
            ->andReturn(GatewayVerificationResult::SUCCESS);
        $this->app->instance(PaymentGatewayInterface::class, $gateway);

        $action = app(ProcessPaymentCallbackAction::class);
        $processed = $action->execute('delayed-success-reference');
        $action->execute('delayed-success-reference');

        $freshSubscription = $subscription->fresh();

        $this->assertSame(PaymentStatus::Successful, $processed->status);
        $this->assertSame(SubscriptionStatus::Active, $freshSubscription->status);
        $this->assertSame($paidAt->timestamp, $freshSubscription->current_period_starts_at?->timestamp);
        $this->assertSame($paidAt->copy()->addMonth()->timestamp, $freshSubscription->current_period_ends_at?->timestamp);
        $this->assertSame(1, $subscription->events()->where('event_type', SubscriptionEventType::PaymentApproved->value)->count());
        $this->assertSame(1, $subscription->events()->where('event_type', SubscriptionEventType::Expired->value)->count());
        $this->assertSame(1, $subscription->events()->where('event_type', SubscriptionEventType::Renewed->value)->count());
    }

    public function test_callback_winning_after_expiration_scan_preflight_prevents_stale_expiration(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-12 10:00:00', 'UTC'));

        $business = $this->createBusiness();
        $plan = $this->createPlan('starter', 1000, ['menu_limit' => 10]);
        $subscription = app(StartTrialAction::class)->execute($business, $plan);
        $trialEndsAt = $subscription->trial_ends_at;
        $payment = $subscription->payments()->create([
            'target_plan_id' => $plan->getKey(),
            'amount' => $plan->monthly_price,
            'status' => PaymentStatus::Pending,
            'gateway_reference' => 'callback-expiration-race-reference',
        ]);

        self::assertNotNull($trialEndsAt);
        Carbon::setTestNow($trialEndsAt->copy()->addSecond());

        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('verifyPayment')
            ->once()
            ->andReturn(GatewayVerificationResult::SUCCESS);
        $this->app->instance(PaymentGatewayInterface::class, $gateway);

        app(ProcessPaymentCallbackAction::class)->execute('callback-expiration-race-reference');

        // The expiration query may have selected this row before the callback committed.
        // Its locked recheck must observe Active and leave the renewed period untouched.
        $this->assertSame(0, app(ExpireSubscriptionsAction::class)->execute());
        $this->assertSame(PaymentStatus::Successful, $payment->fresh()->status);
        $this->assertSame(SubscriptionStatus::Active, $subscription->fresh()->status);
        $paidAt = $payment->fresh()->paid_at;
        self::assertNotNull($paidAt);
        $this->assertSame(
            $paidAt->copy()->addMonth()->timestamp,
            $subscription->fresh()->current_period_ends_at?->timestamp,
        );
        $this->assertSame(0, $subscription->events()->where('event_type', SubscriptionEventType::Expired->value)->count());
    }

    /**
     * @param  array<string, bool|int|float|string|null>  $features
     */
    private function createPlan(string $code, int $monthlyPrice, array $features): Plan
    {
        $plan = Plan::query()->create([
            'code' => $code,
            'name' => ucfirst($code),
            'monthly_price' => $monthlyPrice,
            'is_active' => true,
        ]);

        foreach ($features as $featureKey => $value) {
            PlanFeature::query()->create([
                'plan_id' => $plan->getKey(),
                'feature_key' => $featureKey,
                'value' => $value,
            ]);
        }

        return $plan;
    }

    private function createBusiness(): Business
    {
        return Business::query()->create([
            'name' => 'Lifecycle Test Business',
            'slug' => 'lifecycle-test-'.uniqid(),
        ]);
    }
}
