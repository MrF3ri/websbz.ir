<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Modules\Subscription\Enums\SubscriptionEventType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithBusinesses;
use Tests\TestCase;

class AdminEventCenterTest extends TestCase
{
    use InteractsWithBusinesses;
    use RefreshDatabase;

    public function test_platform_admin_can_filter_events_and_only_receives_safe_event_fields(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_platform_admin' => true])->save();

        $business = $this->createBusinessFor($admin, attributes: [
            'name' => 'North Star Cafe',
            'slug' => 'north-star-cafe',
        ]);
        $subscription = $this->giveBusinessTrialSubscription($business, 'professional');

        $event = $subscription->events()->create([
            'event_type' => SubscriptionEventType::PaymentSubmitted,
            'metadata' => [
                'payment_id' => 123,
                'amount' => 450000,
                'bank_tracking_number' => 'EVENT-TRACK-001',
                'receipt_path' => 'private/receipts/do-not-expose.pdf',
                'reason' => 'private internal note',
            ],
            'created_at' => now(),
        ]);

        $subscription->events()->create([
            'event_type' => SubscriptionEventType::TrialStarted,
            'metadata' => ['plan_id' => 3],
            'created_at' => now()->subMinute(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.events.index', [
                'event_type' => SubscriptionEventType::PaymentSubmitted->value,
                'search' => 'NORTH STAR',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Events/Index')
                ->where('filters.event_type', SubscriptionEventType::PaymentSubmitted->value)
                ->where('filters.search', 'NORTH STAR')
                ->where('events.total', 1)
                ->has('events.data', 1)
                ->where('events.data.0.id', $event->getKey())
                ->where('events.data.0.type', SubscriptionEventType::PaymentSubmitted->value)
                ->where('events.data.0.business_public_id', $business->public_id)
                ->where('events.data.0.business_name', 'North Star Cafe')
                ->where('events.data.0.plan_name', 'Professional')
                ->missing('events.data.0.metadata')
                ->missing('events.data.0.receipt_path')
                ->has('eventTypes', count(SubscriptionEventType::cases())));
    }

    public function test_event_center_search_does_not_return_events_from_other_businesses(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_platform_admin' => true])->save();

        $firstBusiness = $this->createBusinessFor($admin, attributes: [
            'name' => 'First Event Cafe',
            'slug' => 'first-event-cafe',
        ]);
        $secondOwner = User::factory()->create();
        $secondBusiness = $this->createBusinessFor($secondOwner, attributes: [
            'name' => 'Second Event Cafe',
            'slug' => 'second-event-cafe',
        ]);

        $firstSubscription = $this->giveBusinessTrialSubscription($firstBusiness, 'economic');
        $secondSubscription = $this->giveBusinessTrialSubscription($secondBusiness, 'economic');

        $firstSubscription->events()->create([
            'event_type' => SubscriptionEventType::TrialStarted,
            'metadata' => [],
            'created_at' => now(),
        ]);
        $secondSubscription->events()->create([
            'event_type' => SubscriptionEventType::TrialStarted,
            'metadata' => [],
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.events.index', ['search' => 'First Event']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('events.total', 1)
                ->where('events.data.0.business_name', 'First Event Cafe'));
    }

    public function test_non_platform_admin_cannot_view_event_center(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.events.index'))
            ->assertForbidden();
    }

    public function test_event_center_rejects_unknown_event_type_filter(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_platform_admin' => true])->save();

        $this->actingAs($admin)
            ->get(route('admin.events.index', ['event_type' => 'arbitrary_internal_event']))
            ->assertSessionHasErrors('event_type');
    }
}
