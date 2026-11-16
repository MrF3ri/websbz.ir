<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Modules\Platform\Models\PlatformAuditLog;
use App\Modules\Subscription\Actions\ReviewManualPaymentAction;
use App\Modules\Subscription\Actions\SubmitManualPaymentAction;
use App\Modules\Subscription\Enums\PaymentStatus;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use App\Modules\Subscription\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithBusinesses;
use Tests\TestCase;

class PaymentReviewAuditTest extends TestCase
{
    use InteractsWithBusinesses;
    use RefreshDatabase;

    public function test_approval_is_audited_transactionally_with_safe_before_and_after_snapshots(): void
    {
        $owner = User::factory()->create();
        $business = $this->createBusinessFor($owner);
        $subscription = $this->giveBusinessTrialSubscription($business, 'economic');
        $targetPlan = Plan::query()->where('code', 'professional')->firstOrFail();
        $receiptPath = 'payment-receipts/'.$business->getKey().'/private-approval-receipt.pdf';
        $trackingNumber = 'PRIVATE-APPROVAL-TRACKING-001';
        $payment = app(SubmitManualPaymentAction::class)->execute(
            $subscription,
            $targetPlan,
            $trackingNumber,
            $receiptPath,
        );

        $admin = User::factory()->create();
        $admin->forceFill(['is_platform_admin' => true])->save();

        $review = app(ReviewManualPaymentAction::class);
        $review->approve($payment, $admin);
        $review->approve($payment, $admin);

        $log = PlatformAuditLog::query()
            ->where('action', 'platform.payment.review.approved')
            ->sole();

        $this->assertSame($admin->getKey(), $log->actor_id);
        $this->assertSame('payment', $log->target_type);
        $this->assertSame((string) $payment->getKey(), $log->target_id);
        $this->assertSame(PaymentStatus::PendingReview->value, $log->before_data['payment_status']);
        $this->assertSame(PaymentStatus::Approved->value, $log->after_data['payment_status']);
        $this->assertSame(SubscriptionStatus::Trialing->value, $log->before_data['subscription_status']);
        $this->assertSame(SubscriptionStatus::Active->value, $log->after_data['subscription_status']);
        $this->assertSame($targetPlan->getKey(), $log->after_data['subscription_plan_id']);
        $this->assertSame(1, PlatformAuditLog::query()->count());

        $serialized = json_encode($log->toArray(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($receiptPath, $serialized);
        $this->assertStringNotContainsString($trackingNumber, $serialized);
        $this->assertArrayNotHasKey('receipt_path', $log->after_data);
        $this->assertArrayNotHasKey('bank_tracking_number', $log->after_data);
    }

    public function test_rejection_is_audited_without_copying_reason_or_private_payment_data(): void
    {
        $owner = User::factory()->create();
        $business = $this->createBusinessFor($owner);
        $subscription = $this->giveBusinessTrialSubscription($business, 'economic');
        $originalPlanId = $subscription->plan_id;
        $receiptPath = 'payment-receipts/'.$business->getKey().'/private-rejection-receipt.pdf';
        $trackingNumber = 'PRIVATE-REJECTION-TRACKING-002';
        $reason = 'PRIVATE-REJECTION-REASON-DO-NOT-AUDIT';
        $payment = app(SubmitManualPaymentAction::class)->execute(
            $subscription,
            Plan::query()->where('code', 'professional')->firstOrFail(),
            $trackingNumber,
            $receiptPath,
        );

        $admin = User::factory()->create();
        $admin->forceFill(['is_platform_admin' => true])->save();

        app(ReviewManualPaymentAction::class)->reject($payment, $admin, $reason);

        $log = PlatformAuditLog::query()
            ->where('action', 'platform.payment.review.rejected')
            ->sole();

        $this->assertSame($admin->getKey(), $log->actor_id);
        $this->assertSame(PaymentStatus::PendingReview->value, $log->before_data['payment_status']);
        $this->assertSame(PaymentStatus::Rejected->value, $log->after_data['payment_status']);
        $this->assertSame(SubscriptionStatus::Trialing->value, $log->before_data['subscription_status']);
        $this->assertSame(SubscriptionStatus::Trialing->value, $log->after_data['subscription_status']);
        $this->assertSame($originalPlanId, $log->before_data['subscription_plan_id']);
        $this->assertSame($originalPlanId, $log->after_data['subscription_plan_id']);

        $serialized = json_encode($log->toArray(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($reason, $serialized);
        $this->assertStringNotContainsString($receiptPath, $serialized);
        $this->assertStringNotContainsString($trackingNumber, $serialized);
        $this->assertArrayNotHasKey('rejection_reason', $log->after_data);
        $this->assertArrayNotHasKey('receipt_path', $log->after_data);
        $this->assertArrayNotHasKey('bank_tracking_number', $log->after_data);
    }
}
