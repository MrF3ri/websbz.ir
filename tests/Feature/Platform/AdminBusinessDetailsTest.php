<?php

namespace Tests\\Feature\\Platform;

use App\\Enums\\BusinessRole;
use App\\Models\\User;
use App\\Modules\\Subscription\\Enums\\PaymentStatus;
use App\\Modules\\Subscription\\Enums\\SubscriptionEventType;
use App\\Modules\\Subscription\\Models\\Plan;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Inertia\\Testing\\AssertableInertia as Assert;
use Tests\\Concerns\\InteractsWithBusinesses;
use Tests\\TestCase;

class AdminBusinessDetailsTest extends TestCase
{
    use InteractsWithBusinesses;
    use RefreshDatabase;

    public function test_platform_admin_can_view_business_details_members_subscription_payments_and_events(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_platform_admin' => true])->save();

        $business = $this->createBusinessFor($admin, attributes: [
            'name' => 'North Star Cafe',
            'slug' => 'north-star-cafe',
            'phone' => '02112345678',
            'email' => 'hello@example.test',
            'city' => 'Tehran',
        ]);

        $subscription = $this->giveBusinessTrialSubscription($business, 'professional');
        $plan = Plan::query()->where('code', 'professional')->firstOrFail();

        $payment = $subscription->payments()->create([
            'target_plan_id' => $plan->getKey(),
            'amount' => $plan->monthly_price,
            'status' => PaymentStatus::PendingReview,
            'bank_tracking_number' => 'BANK-DETAILS-001',
            'receipt_path' => 'payment-receipts/'.$business->getKey().'/private.pdf',
            'submitted_at' => now(),
        ]);

        $subscription->events()->create([
            'event_type' => SubscriptionEventType::PaymentSubmitted,
            'metadata' => ['internal_note' => 'must not be exposed'],
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.businesses.show', $business->public_id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Businesses/Show')
                ->where('business.public_id', $business->public_id)
                ->where('business.name', 'North Star Cafe')
                ->where('business.city', 'Tehran')
                ->missing('business.id')
                ->where('owner.email', $admin->email)
                ->where('members.0.role', BusinessRole::Owner->value)
                ->where('subscription.plan', 'Professional')
                ->where('subscription.status', 'trialing')
                ->where('payments.0.id', $payment->getKey())
                ->where('payments.0.bank_tracking_number', 'BANK-DETAILS-001')
                ->where('payments.0.receipt_url', route('admin.payments.receipt', $payment))
                ->missing('payments.0.receipt_path')
                ->where('events.0.type', SubscriptionEventType::PaymentSubmitted->value)
                ->missing('events.0.metadata'));
    }

    public function test_non_platform_admin_cannot_view_business_details(): void
    {
        $owner = User::factory()->create();
        $business = $this->createBusinessFor($owner);
        $ordinaryUser = User::factory()->create();

        $this->actingAs($ordinaryUser)
            ->get(route('admin.businesses.show', $business->public_id))
            ->assertForbidden();
    }

    public function test_unknown_public_business_id_returns_not_found(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_platform_admin' => true])->save();

        $this->actingAs($admin)
            ->get(route('admin.businesses.show', '01J00000000000000000000000'))
            ->assertNotFound();
    }
}
