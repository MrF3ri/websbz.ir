<?php

namespace Tests\Feature\Inertia;

use App\Enums\BusinessRole;
use App\Models\User;
use App\Modules\Business\Models\Business;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithBusinesses;
use Tests\TestCase;

class EntitlementsSharedPropsTest extends TestCase
{
    use InteractsWithBusinesses;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(PlanSeeder::class);
    }

    public function test_authenticated_current_business_receives_only_safe_entitlement_props(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = $this->createBusinessFor($user);
        $this->giveBusinessTrialSubscription($business, 'professional');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('entitlements.plan_code', 'professional')
                ->where('entitlements.status', 'trialing')
                ->where('entitlements.features.max_products', -1)
                ->where('entitlements.features.product_discount', true)
                ->has('entitlements.trial_ends_at')
                ->missing('entitlements.business_id')
                ->missing('entitlements.subscription_id')
                ->missing('entitlements.plan_version'));
    }

    public function test_entitlements_are_null_when_user_has_no_current_business_or_subscription(): void
    {
        $userWithoutBusiness = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($userWithoutBusiness)
            ->get(route('onboarding'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('entitlements', null));

        $userWithoutSubscription = User::factory()->create(['email_verified_at' => now()]);
        $this->createBusinessFor($userWithoutSubscription);

        $this->actingAs($userWithoutSubscription)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('entitlements', null));
    }

    public function test_foreign_session_business_cannot_leak_its_entitlements_into_current_users_props(): void
    {
        $userA = User::factory()->create(['email_verified_at' => now()]);
        $userB = User::factory()->create(['email_verified_at' => now()]);
        $businessA = $this->createBusinessFor($userA, BusinessRole::Owner, ['name' => 'A']);
        $businessB = $this->createBusinessFor($userB, BusinessRole::Owner, ['name' => 'B']);

        $this->giveBusinessTrialSubscription($businessA, 'economic');
        $this->giveBusinessTrialSubscription($businessB, 'professional');

        $this->actingAs($userA)
            ->withSession(['active_business_id' => $businessB->getKey()])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('entitlements.plan_code', 'economic')
                ->where('entitlements.features.max_products', 20)
                ->where('entitlements.features.product_discount', false));
    }
}
