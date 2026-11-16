<?php

namespace Tests\Feature\Subscription;

use App\Models\User;
use App\Modules\Platform\Models\PlatformAuditLog;
use App\Modules\Platform\Services\PlatformSettingsService;
use App\Modules\Subscription\Enums\PaymentStatus;
use App\Modules\Subscription\Enums\SubscriptionEventType;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use App\Modules\Subscription\Models\Payment;
use App\Modules\Subscription\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithBusinesses;
use Tests\TestCase;

class ManualPaymentApprovalFlowTest extends TestCase
{
    use InteractsWithBusinesses;
    use RefreshDatabase;

    public function test_payment_submission_and_admin_approval_activate_the_paid_plan_and_preserve_receipt_privacy(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-16 12:00:00', 'UTC'));
        Storage::fake('local');

        try {
            $owner = User::factory()->create();
            $business = $this->createBusinessFor($owner);
            $subscription = $this->giveBusinessTrialSubscription($business, 'economic');
            $trialStartsAt = $subscription->current_period_starts_at;
            $trialEndsAt = $subscription->trial_ends_at;
            $targetPlan = Plan::query()->where('code', 'professional')->firstOrFail();

            $admin = User::factory()->create();
            $admin->forceFill(['is_platform_admin' => true])->save();

            app(PlatformSettingsService::class)->updateCardDetails([
                'bank_name' => 'ملت',
                'card_number' => '1234567812345678',
                'card_holder' => 'Platform Owner',
            ], $admin);

            $trackingNumber = 'E2E-APPROVAL-TRACKING-001';
            $this->actingAs($owner)
                ->post(route('billing.payments.store'), [
                    'plan_id' => $targetPlan->getKey(),
                    'bank_tracking_number' => $trackingNumber,
                    'receipt' => UploadedFile::fake()->create('approval-receipt.pdf', 50, 'application/pdf'),
                ])
                ->assertRedirect()
                ->assertSessionHasNoErrors();

            $payment = Payment::query()->sole();
            $this->assertSame(PaymentStatus::PendingReview, $payment->status);
            $this->assertSame(750_000, $payment->amount);
            Storage::disk('local')->assertExists($payment->receipt_path);

            $this->actingAs($admin)
                ->post(route('admin.payments.approve', $payment))
                ->assertRedirect()
                ->assertSessionHasNoErrors();

            $payment->refresh();
            $subscription->refresh();

            $this->assertSame(PaymentStatus::Approved, $payment->status);
            $this->assertNotNull($payment->paid_at);
            $this->assertSame(SubscriptionStatus::Active, $subscription->status);
            $this->assertSame($targetPlan->getKey(), $subscription->plan_id);
            $this->assertSame($trialStartsAt?->toISOString(), $subscription->current_period_starts_at?->toISOString());
            $this->assertSame(
                $trialEndsAt?->copy()->addMonthNoOverflow()->toISOString(),
                $subscription->current_period_ends_at?->toISOString(),
            );
            $this->assertSame(
                1,
                $subscription->events()->where('event_type', SubscriptionEventType::PaymentApproved)->count(),
            );

            $audit = PlatformAuditLog::query()
                ->where('action', 'platform.payment.review.approved')
                ->sole();

            $this->assertSame($admin->getKey(), $audit->actor_id);
            $this->assertSame((string) $payment->getKey(), $audit->target_id);
            $serializedAudit = json_encode($audit->toArray(), JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString($trackingNumber, $serializedAudit);
            $this->assertStringNotContainsString($payment->receipt_path, $serializedAudit);
            $this->assertArrayNotHasKey('bank_tracking_number', $audit->after_data);
            $this->assertArrayNotHasKey('receipt_path', $audit->after_data);

            $this->actingAs($owner)
                ->get(route('billing.index'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Billing/Index')
                    ->where('subscription.status', SubscriptionStatus::Active->value)
                    ->where('subscription.plan.id', $targetPlan->getKey())
                    ->where('payments.0.status', PaymentStatus::Approved->value)
                    ->where('payments.0.receipt_url', route('billing.payments.receipt', $payment)));

            $this->actingAs($owner)
                ->get(route('billing.payments.receipt', $payment))
                ->assertOk();

            $this->actingAs($admin)
                ->get(route('admin.payments.receipt', $payment))
                ->assertOk();

            $otherOwner = User::factory()->create();
            $otherBusiness = $this->createBusinessFor($otherOwner);
            $this->giveBusinessTrialSubscription($otherBusiness, 'economic');

            $this->actingAs($otherOwner)
                ->get(route('billing.payments.receipt', $payment))
                ->assertNotFound();

            Storage::disk('local')->assertExists($payment->receipt_path);
        } finally {
            Carbon::setTestNow();
        }
    }
}
