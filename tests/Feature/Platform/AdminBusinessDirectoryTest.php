<?php

namespace Tests\Feature\Platform;

use App\Enums\BusinessStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithBusinesses;
use Tests\TestCase;

class AdminBusinessDirectoryTest extends TestCase
{
    use InteractsWithBusinesses;
    use RefreshDatabase;

    public function test_platform_admin_can_search_and_filter_businesses_with_subscription_summary(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_platform_admin' => true])->save();

        $this->createBusinessFor($admin, attributes: [
            'name' => 'Alpha Coffee',
            'slug' => 'alpha-coffee',
            'status' => BusinessStatus::Active,
        ]);

        $suspended = $this->createBusinessFor($admin, attributes: [
            'name' => 'Beta Roastery',
            'slug' => 'beta-roastery',
            'status' => BusinessStatus::Suspended,
        ]);

        $this->giveBusinessTrialSubscription($suspended, 'professional');

        $this->actingAs($admin)
            ->get(route('admin.businesses.index', [
                'search' => 'BETA',
                'status' => BusinessStatus::Suspended->value,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Businesses/Index')
                ->where('filters.search', 'BETA')
                ->where('filters.status', BusinessStatus::Suspended->value)
                ->where('businesses.total', 1)
                ->has('businesses.data', 1)
                ->where('businesses.data.0.name', 'Beta Roastery')
                ->where('businesses.data.0.slug', 'beta-roastery')
                ->where('businesses.data.0.status', BusinessStatus::Suspended->value)
                ->where('businesses.data.0.members_count', 1)
                ->where('businesses.data.0.subscription.plan', 'Professional')
                ->where('businesses.data.0.subscription.status', 'trialing'));
    }

    public function test_non_platform_admin_cannot_list_businesses(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.businesses.index'))
            ->assertForbidden();
    }
}
