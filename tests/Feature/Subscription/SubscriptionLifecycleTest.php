<?php

namespace Tests\Feature\Subscription;

use App\Modules\Business\Models\Business;
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
