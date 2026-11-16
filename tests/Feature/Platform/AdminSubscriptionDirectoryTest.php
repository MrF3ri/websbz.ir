<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithBusinesses;
use Tests\TestCase;

class AdminSubscriptionDirectoryTest extends TestCase
{
    use InteractsWithBusinesses;
    use RefreshDatabase;

    public function test_platform_admin_can_filter_subscriptions_by_status_plan_and_business(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_platform_admin' => true])->save();

        $business = $this->createBusinessFor($admin, attributes: [
            'name' => 'Directory Test Cafe',
            'slug' => 'directory-test-cafe',
        ]);

        $this->giveBusinessTrialSubscription($business, 'economic');

        $this->actingAs($admin)
            ->get(route('admin.subscriptions.index', [
                'status' => 'trialing',
                'plan' => 'economic',
                'search' => 'DIRECTORY',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Subscriptions/Index')
                ->where('filters.status', 'trialing')
                ->where('filters.plan', 'economic')
                ->where('filters.search', 'DIRECTORY')
                ->where('subscriptions.total', 1)
                ->has('subscriptions.data', 1)
                ->where('subscriptions.data.0.business_name', 'Directory Test Cafe')
                ->where('subscriptions.data.0.plan_code', 'economic')
                ->where('subscriptions.data.0.status', 'trialing')
                ->has('plans', 3)
                ->has('statuses', 5));
    }

    public function test_non_platform_admin_cannot_list_subscriptions(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.subscriptions.index'))
            ->assertForbidden();
    }
}
