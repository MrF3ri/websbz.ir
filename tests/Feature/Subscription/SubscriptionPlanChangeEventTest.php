<?php

namespace Tests\Feature\Subscription;

use App\Models\User;
use App\Modules\Subscription\Actions\ProcessPaymentCallbackAction;
use App\Modules\Subscription\Actions\ReviewManualPaymentAction;
use App\Modules\Subscription\Actions\SubmitManualPaymentAction;
use App\Modules\Subscription\Contracts\PaymentGatewayInterface;
use App\Modules\Subscription\Enums\GatewayVerificationResult;
use App\Modules\Subscription\Enums\PaymentStatus;
use App\Modules\Subscription\Enums\SubscriptionEventType;
use App\Modules\Subscription\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Concerns\InteractsWithBusinesses;
use Tests\TestCase;

class SubscriptionPlanChangeEventTest extends TestCase
{
    use InteractsWithBusinesses;
    use RefreshDatabase;

    public function test_manual_payment_approval_records_downgrade_instead_of_upgrade(): void
    {
        $owner = User::factory()->create();
        $business = $this->createBusinessFor($owner);
        $subscription = $this->giveBusinessTrialSubscription($business, 'top');
        $targetPlan = Plan::query()->where('code', 'economic')->firstOrFail();

        $payment = app(SubmitManualPaymentAction::class)->execute(
            $subscription,
            $targetPlan,
            'BANK-DOWNGRADE-MANUAL-001',
            'payment-receipts/test/manual-downgrade.pdf',
        );

        $admin = User::factory()->create();
        $admin->forceFill(['is_platform_admin' => true])->save();

        app(ReviewManualPaymentAction::class)->approve($payment, $admin);

        $this->assertSame($targetPlan->getKey(), $subscription->fresh()->plan_id);
        $this->assertDatabaseHas('subscription_events', [
            'subscription_id' => $subscription->getKey(),
            'event_type' => SubscriptionEventType::Downgraded->value,
        ]);
        $this->assertDatabaseMissing('subscription_events', [
            'subscription_id' => $subscription->getKey(),
            'event_type' => SubscriptionEventType::Upgraded->value,
        ]);
    }

    public function test_verified_gateway_callback_records_downgrade_instead_of_upgrade(): void
    {
        $owner = User::factory()->create();
        $business = $this->createBusinessFor($owner);
        $subscription = $this->giveBusinessTrialSubscription($business, 'top');
        $targetPlan = Plan::query()->where('code', 'economic')->firstOrFail();

        $payment = $subscription->payments()->create([
            'target_plan_id' => $targetPlan->getKey(),
            'amount' => $targetPlan->monthly_price,
            'status' => PaymentStatus::Pending,
            'gateway_reference' => 'gateway-downgrade-001',
        ]);

        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('verifyPayment')
            ->once()
            ->with('gateway-downgrade-001')
            ->andReturn(GatewayVerificationResult::SUCCESS);
        $this->app->instance(PaymentGatewayInterface::class, $gateway);

        $processed = app(ProcessPaymentCallbackAction::class)->execute('gateway-downgrade-001');

        $this->assertSame(PaymentStatus::Successful, $processed->status);
        $this->assertSame($targetPlan->getKey(), $subscription->fresh()->plan_id);
        $this->assertDatabaseHas('subscription_events', [
            'subscription_id' => $subscription->getKey(),
            'event_type' => SubscriptionEventType::Downgraded->value,
        ]);
        $this->assertDatabaseMissing('subscription_events', [
            'subscription_id' => $subscription->getKey(),
            'event_type' => SubscriptionEventType::Upgraded->value,
        ]);
        $this->assertSame($payment->getKey(), $processed->getKey());
    }
}
