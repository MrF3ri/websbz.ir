<?php

namespace Tests\Feature\Subscription;

use App\Models\User;
use App\Modules\Subscription\Actions\ReviewManualPaymentAction;
use App\Modules\Subscription\Actions\SubmitManualPaymentAction;
use App\Modules\Subscription\Enums\PaymentStatus;
use App\Modules\Subscription\Enums\SubscriptionEventType;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use App\Modules\Subscription\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithBusinesses;
use Tests\TestCase;

class ManualPaymentReviewTest extends TestCase
{
    use InteractsWithBusinesses;
    use RefreshDatabase;

    public function test_approval_activates_selected_plan_and_is_idempotent(): void
    {
        $owner = User::factory()->create();
        $business = $this->createBusinessFor($owner);
        $subscription = $this->giveBusinessTrialSubscription($business, 'economic');
        $plan = Plan::query()->where('code', 'professional')->firstOrFail();
        $payment = app(SubmitManualPaymentAction::class)->execute(
            $subscription,
            $plan,
            'BANK-APPROVE-001',
            'payment-receipts/1/receipt.pdf',
        );
        $admin = User::factory()->create();
        $admin->forceFill(['is_platform_admin' => true])->save();

        $review = app(ReviewManualPaymentAction::class);
        $approved = $review->approve($payment, $admin);
        $review->approve($payment, $admin);

        $this->assertSame(PaymentStatus::Approved, $approved->fresh()->status);
        $this->assertSame(SubscriptionStatus::Active, $subscription->fresh()->status);
        $this->assertSame($plan->getKey(), $subscription->fresh()->plan_id);
        $this->assertSame(750_000, $approved->amount);
        $this->assertSame(1, $subscription->events()->where('event_type', SubscriptionEventType::PaymentApproved)->count());
        $this->assertSame(1, $subscription->events()->where('event_type', SubscriptionEventType::Upgraded)->count());
    }

    public function test_rejection_keeps_trial_subscription_unchanged(): void
    {
        $owner = User::factory()->create();
        $business = $this->createBusinessFor($owner);
        $subscription = $this->giveBusinessTrialSubscription($business, 'economic');
        $plan = Plan::query()->where('code', 'professional')->firstOrFail();
        $payment = app(SubmitManualPaymentAction::class)->execute(
            $subscription,
            $plan,
            'BANK-REJECT-001',
            'payment-receipts/1/receipt.pdf',
        );
        $admin = User::factory()->create();
        $admin->forceFill(['is_platform_admin' => true])->save();

        $rejected = app(ReviewManualPaymentAction::class)
            ->reject($payment, $admin, 'رسید با اطلاعات بانکی مطابقت ندارد.');

        $this->assertSame(PaymentStatus::Rejected, $rejected->status);
        $this->assertSame('رسید با اطلاعات بانکی مطابقت ندارد.', $rejected->rejection_reason);
        $this->assertSame(SubscriptionStatus::Trialing, $subscription->fresh()->status);
        $this->assertSame('economic', $subscription->fresh()->plan->code);
    }
}
