<?php

namespace Tests\Feature\Subscription;

use App\Models\User;
use App\Modules\Subscription\Actions\ReviewManualPaymentAction;
use App\Modules\Subscription\Actions\SubmitManualPaymentAction;
use App\Modules\Subscription\Enums\PaymentStatus;
use App\Modules\Subscription\Enums\SubscriptionEventType;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Subscription\Models\PlanFeature;
use App\Modules\Subscription\Services\EntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
        $expectedSnapshot = $payment->target_features_snapshot;

        PlanFeature::query()
            ->where('plan_id', $plan->getKey())
            ->where('feature_key', 'max_products')
            ->update(['value' => 999]);

        $admin = User::factory()->create();
        $admin->forceFill(['is_platform_admin' => true])->save();

        $review = app(ReviewManualPaymentAction::class);
        $approved = $review->approve($payment, $admin);
        $review->approve($payment, $admin);

        $this->assertSame(PaymentStatus::Approved, $approved->fresh()->status);
        $this->assertSame(SubscriptionStatus::Active, $subscription->fresh()->status);
        $this->assertSame($plan->getKey(), $subscription->fresh()->plan_id);
        $this->assertSame($expectedSnapshot, $subscription->fresh()->features_snapshot);
        $this->assertSame(750_000, $approved->amount);
        $this->assertSame(1, $subscription->events()->where('event_type', SubscriptionEventType::PaymentApproved)->count());
        $this->assertSame(1, $subscription->events()->where('event_type', SubscriptionEventType::Upgraded)->count());
    }

    public function test_delayed_approval_starts_a_fresh_paid_period_after_trial_expiry(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-15 10:00:00', 'UTC'));

        try {
            $owner = User::factory()->create();
            $business = $this->createBusinessFor($owner);
            $subscription = $this->giveBusinessTrialSubscription($business, 'economic');
            $trialEndsAt = $subscription->trial_ends_at;
            $plan = Plan::query()->where('code', 'professional')->firstOrFail();
            $payment = app(SubmitManualPaymentAction::class)->execute(
                $subscription,
                $plan,
                'BANK-DELAYED-APPROVAL-001',
                'payment-receipts/1/delayed-receipt.pdf',
            );

            self::assertNotNull($trialEndsAt);
            Carbon::setTestNow($trialEndsAt->copy()->addDays(2));

            $admin = User::factory()->create();
            $admin->forceFill(['is_platform_admin' => true])->save();

            $approved = app(ReviewManualPaymentAction::class)->approve($payment, $admin);
            $freshSubscription = $subscription->fresh();
            $paidAt = Carbon::now();

            $this->assertSame(PaymentStatus::Approved, $approved->status);
            $this->assertSame(SubscriptionStatus::Active, $freshSubscription->status);
            $this->assertSame($paidAt->timestamp, $freshSubscription->current_period_starts_at?->timestamp);
            $this->assertSame(
                $paidAt->copy()->addMonth()->timestamp,
                $freshSubscription->current_period_ends_at?->timestamp,
            );
            $this->assertSame(
                1,
                $subscription->events()->where('event_type', SubscriptionEventType::PaymentApproved->value)->count(),
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_early_approval_preserves_trial_access_and_starts_paid_month_after_trial(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-16 10:00:00', 'UTC'));

        try {
            $owner = User::factory()->create();
            $business = $this->createBusinessFor($owner);
            $subscription = $this->giveBusinessTrialSubscription($business, 'economic');
            $trialStartsAt = $subscription->current_period_starts_at;
            $trialEndsAt = $subscription->trial_ends_at;
            $plan = Plan::query()->where('code', 'professional')->firstOrFail();
            $payment = app(SubmitManualPaymentAction::class)->execute(
                $subscription,
                $plan,
                'BANK-EARLY-APPROVAL-001',
                'payment-receipts/1/early-approval.pdf',
            );

            self::assertNotNull($trialStartsAt);
            self::assertNotNull($trialEndsAt);

            $admin = User::factory()->create();
            $admin->forceFill(['is_platform_admin' => true])->save();

            app(ReviewManualPaymentAction::class)->approve($payment, $admin);

            $freshSubscription = $subscription->fresh();
            $this->assertSame($trialStartsAt->timestamp, $freshSubscription->current_period_starts_at?->timestamp);
            $this->assertSame(
                $trialEndsAt->copy()->addMonth()->timestamp,
                $freshSubscription->current_period_ends_at?->timestamp,
            );

            $expectedLimit = $payment->target_features_snapshot['max_products'];
            $this->assertSame(
                $expectedLimit,
                (new EntitlementService($business))->getLimit('max_products'),
            );

            Carbon::setTestNow($trialEndsAt);
            $this->assertSame(
                $expectedLimit,
                (new EntitlementService($business))->getLimit('max_products'),
            );

            Carbon::setTestNow($freshSubscription->current_period_ends_at);
            $this->assertNull((new EntitlementService($business))->getLimit('max_products'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_early_paid_renewal_preserves_current_access_until_extended_period_end(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-16 10:00:00', 'UTC'));

        try {
            $owner = User::factory()->create();
            $business = $this->createBusinessFor($owner);
            $subscription = $this->giveBusinessTrialSubscription($business, 'economic');
            $periodStartsAt = Carbon::now()->subDays(15);
            $periodEndsAt = Carbon::now()->addDays(15);
            $subscription->update([
                'status' => SubscriptionStatus::Active,
                'trial_ends_at' => Carbon::now()->subDays(30),
                'current_period_starts_at' => $periodStartsAt,
                'current_period_ends_at' => $periodEndsAt,
            ]);

            $plan = Plan::query()->where('code', 'professional')->firstOrFail();
            $payment = app(SubmitManualPaymentAction::class)->execute(
                $subscription,
                $plan,
                'BANK-EARLY-RENEWAL-001',
                'payment-receipts/1/early-renewal.pdf',
            );
            $admin = User::factory()->create();
            $admin->forceFill(['is_platform_admin' => true])->save();

            app(ReviewManualPaymentAction::class)->approve($payment, $admin);

            $freshSubscription = $subscription->fresh();
            $this->assertSame($periodStartsAt->timestamp, $freshSubscription->current_period_starts_at?->timestamp);
            $this->assertSame(
                $periodEndsAt->copy()->addMonth()->timestamp,
                $freshSubscription->current_period_ends_at?->timestamp,
            );
            $this->assertSame(
                $payment->target_features_snapshot['max_products'],
                (new EntitlementService($business))->getLimit('max_products'),
            );
        } finally {
            Carbon::setTestNow();
        }
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
