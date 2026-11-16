<?php

namespace Tests\Feature\Subscription;

use App\Models\User;
use App\Modules\Platform\Services\PlatformSettingsService;
use App\Modules\Subscription\Enums\PaymentStatus;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use App\Modules\Subscription\Models\Payment;
use App\Modules\Subscription\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithBusinesses;
use Tests\TestCase;

class ManualPaymentSubmissionTest extends TestCase
{
    use InteractsWithBusinesses;
    use RefreshDatabase;

    public function test_business_owner_can_submit_private_receipt_for_server_priced_plan(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $user = User::factory()->create();
        $business = $this->createBusinessFor($user);
        $subscription = $this->giveBusinessTrialSubscription($business, 'economic');
        $plan = Plan::query()->where('code', 'professional')->firstOrFail();

        app(PlatformSettingsService::class)->updateCardDetails([
            'bank_name' => 'ملت',
            'card_number' => '1234567812345678',
            'card_holder' => 'Platform Owner',
        ], $user);

        $this->actingAs($user)
            ->post(route('billing.payments.store'), [
                'plan_id' => $plan->getKey(),
                'bank_tracking_number' => 'BANK-TRACK-001',
                'receipt' => UploadedFile::fake()->create('receipt.pdf', 50, 'application/pdf'),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $payment = Payment::query()->firstOrFail();

        $this->assertSame(PaymentStatus::PendingReview, $payment->status);
        $this->assertSame(750_000, $payment->amount);
        $this->assertSame('BANK-TRACK-001', $payment->bank_tracking_number);
        $this->assertSame(SubscriptionStatus::Trialing, $subscription->fresh()->status);
        Storage::disk('local')->assertExists($payment->receipt_path);
        $this->assertStringStartsWith('payment-receipts/'.$business->getKey().'/', $payment->receipt_path);
        $this->assertNotSame('receipt.pdf', basename($payment->receipt_path));
        Storage::disk('public')->assertMissing($payment->receipt_path);
    }

    public function test_receipt_rejects_content_with_a_mismatched_filename_extension(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $business = $this->createBusinessFor($user);
        $this->giveBusinessTrialSubscription($business, 'economic');
        $plan = Plan::query()->where('code', 'professional')->firstOrFail();

        app(PlatformSettingsService::class)->updateCardDetails([
            'bank_name' => 'ملت',
            'card_number' => '1234567812345678',
            'card_holder' => 'Platform Owner',
        ], $user);

        $this->actingAs($user)
            ->post(route('billing.payments.store'), [
                'plan_id' => $plan->getKey(),
                'bank_tracking_number' => 'BANK-TRACK-BAD-EXT',
                'receipt' => UploadedFile::fake()->createWithContent('receipt.txt', "%PDF-1.4\\nnot a valid uploaded extension"),
            ])
            ->assertSessionHasErrors('receipt');

        $this->assertSame(0, Payment::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_receipt_rejects_disallowed_mime_content_even_when_filename_is_allowed(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $business = $this->createBusinessFor($user);
        $this->giveBusinessTrialSubscription($business, 'economic');
        $plan = Plan::query()->where('code', 'professional')->firstOrFail();

        app(PlatformSettingsService::class)->updateCardDetails([
            'bank_name' => 'ملت',
            'card_number' => '1234567812345678',
            'card_holder' => 'Platform Owner',
        ], $user);

        $this->actingAs($user)
            ->post(route('billing.payments.store'), [
                'plan_id' => $plan->getKey(),
                'bank_tracking_number' => 'BANK-TRACK-BAD-MIME',
                'receipt' => UploadedFile::fake()->createWithContent('receipt.pdf', 'plain text, not a PDF'),
            ])
            ->assertSessionHasErrors('receipt');

        $this->assertSame(0, Payment::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_receipt_rejects_files_larger_than_five_megabytes(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $business = $this->createBusinessFor($user);
        $this->giveBusinessTrialSubscription($business, 'economic');
        $plan = Plan::query()->where('code', 'professional')->firstOrFail();

        app(PlatformSettingsService::class)->updateCardDetails([
            'bank_name' => 'ملت',
            'card_number' => '1234567812345678',
            'card_holder' => 'Platform Owner',
        ], $user);

        $this->actingAs($user)
            ->post(route('billing.payments.store'), [
                'plan_id' => $plan->getKey(),
                'bank_tracking_number' => 'BANK-TRACK-TOO-LARGE',
                'receipt' => UploadedFile::fake()->create('large.pdf', 5121, 'application/pdf'),
            ])
            ->assertSessionHasErrors('receipt');

        $this->assertSame(0, Payment::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_business_admin_can_download_own_receipt_but_not_another_business_receipt(): void
    {
        Storage::fake('local');

        $ownerA = User::factory()->create();
        $businessA = $this->createBusinessFor($ownerA);
        $subscriptionA = $this->giveBusinessTrialSubscription($businessA, 'economic');
        $planA = Plan::query()->where('code', 'economic')->firstOrFail();

        $ownerB = User::factory()->create();
        $businessB = $this->createBusinessFor($ownerB);
        $subscriptionB = $this->giveBusinessTrialSubscription($businessB, 'economic');
        $planB = Plan::query()->where('code', 'economic')->firstOrFail();

        $receiptPathA = 'payment-receipts/'.$businessA->getKey().'/receipt-a.pdf';
        $receiptPathB = 'payment-receipts/'.$businessB->getKey().'/receipt-b.pdf';
        Storage::disk('local')->put($receiptPathA, '%PDF-1.4 business A');
        Storage::disk('local')->put($receiptPathB, '%PDF-1.4 business B');

        $paymentA = $subscriptionA->payments()->create([
            'target_plan_id' => $planA->getKey(),
            'amount' => $planA->monthly_price,
            'status' => PaymentStatus::PendingReview,
            'bank_tracking_number' => 'BANK-OWNER-A-001',
            'receipt_path' => $receiptPathA,
            'submitted_at' => now(),
        ]);

        $paymentB = $subscriptionB->payments()->create([
            'target_plan_id' => $planB->getKey(),
            'amount' => $planB->monthly_price,
            'status' => PaymentStatus::PendingReview,
            'bank_tracking_number' => 'BANK-OWNER-B-001',
            'receipt_path' => $receiptPathB,
            'submitted_at' => now(),
        ]);

        $this->actingAs($ownerA)
            ->get(route('billing.payments.receipt', $paymentA))
            ->assertOk();

        $this->actingAs($ownerA)
            ->get(route('billing.payments.receipt', $paymentB))
            ->assertNotFound();
    }

    public function test_duplicate_bank_tracking_number_is_rejected(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $business = $this->createBusinessFor($user);
        $this->giveBusinessTrialSubscription($business, 'economic');
        $plan = Plan::query()->where('code', 'professional')->firstOrFail();

        app(PlatformSettingsService::class)->updateCardDetails([
            'bank_name' => 'ملت',
            'card_number' => '1234567812345678',
            'card_holder' => 'Platform Owner',
        ], $user);

        $payload = [
            'plan_id' => $plan->getKey(),
            'bank_tracking_number' => 'BANK-TRACK-DUPLICATE',
            'receipt' => UploadedFile::fake()->image('receipt.png'),
        ];

        $this->actingAs($user)
            ->post(route('billing.payments.store'), $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->post(route('billing.payments.store'), [
                ...$payload,
                'receipt' => UploadedFile::fake()->create('second.pdf', 50, 'application/pdf'),
            ])
            ->assertSessionHasErrors('bank_tracking_number');

        $this->assertSame(1, Payment::query()->count());
    }

    public function test_payment_submission_endpoint_is_rate_limited_to_five_attempts_per_minute(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->createBusinessFor($user);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->actingAs($user)
                ->post(route('billing.payments.store'), [])
                ->assertRedirect();
        }

        $this->actingAs($user)
            ->post(route('billing.payments.store'), [])
            ->assertTooManyRequests();
    }
}
