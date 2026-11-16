<?php

namespace Tests\Feature\Subscription;

use App\Models\User;
use App\Modules\Platform\Models\PlatformAuditLog;
use App\Modules\Platform\Services\PlatformSettingsService;
use App\Modules\Subscription\Enums\PaymentStatus;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use App\Modules\Subscription\Models\Payment;
use App\Modules\Subscription\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithBusinesses;
use Tests\TestCase;

class ManualPaymentRejectionFlowTest extends TestCase
{
    use InteractsWithBusinesses;
    use RefreshDatabase;

    public function test_payment_submission_and_admin_rejection_preserve_subscription_and_private_receipt(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $business = $this->createBusinessFor($owner);
        $subscription = $this->giveBusinessTrialSubscription($business, 'economic');
        $targetPlan = Plan::query()->where('code', 'professional')->firstOrFail();

        $admin = User::factory()->create();
        $admin->forceFill(['is_platform_admin' => true])->save();

        app(PlatformSettingsService::class)->updateCardDetails([
            'bank_name' => 'ملت',
            'card_number' => '1234567812345678',
            'card_holder' => 'Platform Owner',
        ], $admin);

        $subscriptionBeforeReview = [
            'status' => $subscription->status,
            'plan_id' => $subscription->plan_id,
            'trial_ends_at' => $subscription->trial_ends_at?->toISOString(),
            'current_period_starts_at' => $subscription->current_period_starts_at?->toISOString(),
            'current_period_ends_at' => $subscription->current_period_ends_at?->toISOString(),
        ];

        $trackingNumber = 'E2E-REJECTION-TRACKING-001';
        $rejectionReason = 'رسید با اطلاعات بانکی مطابقت ندارد.';

        $this->actingAs($owner)
            ->post(route('billing.payments.store'), [
                'plan_id' => $targetPlan->getKey(),
                'bank_tracking_number' => $trackingNumber,
                'receipt' => UploadedFile::fake()->create('receipt.pdf', 50, 'application/pdf'),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $payment = Payment::query()->sole();

        $this->assertSame(PaymentStatus::PendingReview, $payment->status);
        $this->assertSame(750_000, $payment->amount);
        $this->assertSame(SubscriptionStatus::Trialing, $subscription->fresh()->status);
        Storage::disk('local')->assertExists($payment->receipt_path);

        $this->actingAs($admin)
            ->post(route('admin.payments.reject', $payment), [
                'reason' => $rejectionReason,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $payment->refresh();
        $subscription->refresh();

        $this->assertSame(PaymentStatus::Rejected, $payment->status);
        $this->assertSame($rejectionReason, $payment->rejection_reason);
        $this->assertSame($subscriptionBeforeReview['status'], $subscription->status);
        $this->assertSame($subscriptionBeforeReview['plan_id'], $subscription->plan_id);
        $this->assertSame($subscriptionBeforeReview['trial_ends_at'], $subscription->trial_ends_at?->toISOString());
        $this->assertSame(
            $subscriptionBeforeReview['current_period_starts_at'],
            $subscription->current_period_starts_at?->toISOString(),
        );
        $this->assertSame(
            $subscriptionBeforeReview['current_period_ends_at'],
            $subscription->current_period_ends_at?->toISOString(),
        );

        $audit = PlatformAuditLog::query()
            ->where('action', 'platform.payment.review.rejected')
            ->sole();

        $this->assertSame($admin->getKey(), $audit->actor_id);
        $this->assertSame((string) $payment->getKey(), $audit->target_id);

        $serializedAudit = json_encode($audit->toArray(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($trackingNumber, $serializedAudit);
        $this->assertStringNotContainsString($rejectionReason, $serializedAudit);
        $this->assertStringNotContainsString($payment->receipt_path, $serializedAudit);

        $this->actingAs($owner)
            ->get(route('billing.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Billing/Index')
                ->where('subscription.status', SubscriptionStatus::Trialing->value)
                ->where('subscription.plan.id', $subscriptionBeforeReview['plan_id'])
                ->where('payments.0.status', PaymentStatus::Rejected->value)
                ->where('payments.0.rejection_reason', $rejectionReason)
                ->where('payments.0.bank_tracking_number', $trackingNumber)
                ->where('payments.0.receipt_url', route('billing.payments.receipt', $payment)));

        $this->actingAs($owner)
            ->get(route('billing.payments.receipt', $payment))
            ->assertOk();

        Storage::disk('local')->assertExists($payment->receipt_path);
    }
}
