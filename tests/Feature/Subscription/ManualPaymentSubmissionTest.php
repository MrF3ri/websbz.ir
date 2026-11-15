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
                'receipt' => UploadedFile::fake()->image('receipt.png'),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $payment = Payment::query()->firstOrFail();

        $this->assertSame(PaymentStatus::PendingReview, $payment->status);
        $this->assertSame(750_000, $payment->amount);
        $this->assertSame('BANK-TRACK-001', $payment->bank_tracking_number);
        $this->assertSame(SubscriptionStatus::Trialing, $subscription->fresh()->status);
        Storage::disk('local')->assertExists($payment->receipt_path);
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
                'receipt' => UploadedFile::fake()->image('second.png'),
            ])
            ->assertSessionHasErrors('bank_tracking_number');

        $this->assertSame(1, Payment::query()->count());
    }
}
