<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Modules\Platform\Services\PlatformSettingsService;
use App\Modules\Subscription\Enums\PaymentStatus;
use App\Modules\Subscription\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithBusinesses;
use Tests\TestCase;

class PlatformAdminAccessTest extends TestCase
{
    use InteractsWithBusinesses;
    use RefreshDatabase;

    public function test_only_the_granted_platform_admin_can_open_admin_routes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.payments.index'))
            ->assertForbidden();

        $user->forceFill(['is_platform_admin' => true])->save();

        $this->actingAs($user)
            ->get(route('admin.payments.index'))
            ->assertOk();
    }

    public function test_platform_admin_can_update_card_details_and_download_private_receipt(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create();
        $admin->forceFill(['is_platform_admin' => true])->save();

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), [
                'bank_name' => 'ملت',
                'card_number' => '1234567812345678',
                'card_holder' => 'Platform Owner',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('1234567812345678', app(PlatformSettingsService::class)->cardDetails()['card_number']);

        $business = $this->createBusinessFor($admin);
        $subscription = $this->giveBusinessTrialSubscription($business, 'economic');
        $plan = Plan::query()->where('code', 'economic')->firstOrFail();
        $receiptPath = 'payment-receipts/'.$business->getKey().'/receipt.pdf';
        Storage::disk('local')->put($receiptPath, '%PDF-1.4');

        $payment = $subscription->payments()->create([
            'target_plan_id' => $plan->getKey(),
            'amount' => $plan->monthly_price,
            'status' => PaymentStatus::PendingReview,
            'bank_tracking_number' => 'BANK-ADMIN-RECEIPT-001',
            'receipt_path' => $receiptPath,
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.payments.receipt', $payment))
            ->assertOk();

        $ordinaryUser = User::factory()->create();

        $this->actingAs($ordinaryUser)
            ->get(route('admin.payments.receipt', $payment))
            ->assertForbidden();
    }

    public function test_grant_command_allows_only_one_platform_admin(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        Artisan::call('platform-admin:grant', ['email' => $first->email]);

        $this->assertTrue($first->fresh()->is_platform_admin);

        $exitCode = Artisan::call('platform-admin:grant', ['email' => $second->email]);

        $this->assertSame(1, $exitCode);
        $this->assertFalse($second->fresh()->is_platform_admin);
    }
}
