<?php

namespace Tests\Feature\Tenancy;

use App\Enums\BusinessRole;
use App\Models\User;
use App\Modules\Business\Models\Business;
use App\Modules\Tenancy\Http\Middleware\TenantMiddleware;
use App\Modules\Tenancy\Services\CurrentBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\InteractsWithBusinesses;
use Tests\TestCase;

class TenantIsolationContractTest extends TestCase
{
    use InteractsWithBusinesses;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'verified', TenantMiddleware::class])
            ->get('/__testing/current-business-contract', function (CurrentBusiness $current): array {
                return ['business_id' => $current->get()?->getKey()];
            });

        Route::middleware(['web', TenantMiddleware::class])
            ->get('/__testing/current-business-guest-contract', function (CurrentBusiness $current): array {
                return ['business_id' => $current->get()?->getKey()];
            });
    }

    public function test_user_a_cannot_view_or_update_business_b_settings_via_foreign_session_context(): void
    {
        $userA = User::factory()->create(['email_verified_at' => now()]);
        $userB = User::factory()->create(['email_verified_at' => now()]);
        $businessA = $this->createBusinessFor($userA, BusinessRole::Owner, ['name' => 'A']);
        $businessB = $this->createBusinessFor($userB, BusinessRole::Owner, ['name' => 'B']);

        $this->actingAs($userA)
            ->withSession(['active_business_id' => $businessB->getKey()])
            ->get(route('business.settings.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('business.name', 'A'));

        $this->actingAs($userA)
            ->withSession(['active_business_id' => $businessB->getKey()])
            ->put(route('business.settings.update'), [
                'name' => 'Attempted B Update',
                'business_id' => $businessB->getKey(),
            ])
            ->assertRedirect(route('business.settings.edit'));

        $this->assertDatabaseHas('businesses', ['id' => $businessA->getKey(), 'name' => 'Attempted B Update']);
        $this->assertDatabaseHas('businesses', ['id' => $businessB->getKey(), 'name' => 'B']);
    }

    public function test_submitted_business_id_is_ignored_and_current_tenant_is_authoritative(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $own = $this->createBusinessFor($user);
        $foreignOwner = User::factory()->create(['email_verified_at' => now()]);
        $foreign = $this->createBusinessFor($foreignOwner);

        $this->actingAs($user)->put(route('business.settings.update'), [
            'name' => 'Own Business Updated',
            'business_id' => $foreign->getKey(),
        ])->assertRedirect(route('business.settings.edit'));

        $this->assertDatabaseHas('businesses', ['id' => $own->getKey(), 'name' => 'Own Business Updated']);
        $this->assertDatabaseHas('businesses', ['id' => $foreign->getKey(), 'name' => $foreign->name]);
    }

    public function test_stale_session_active_business_id_falls_back_to_the_users_own_business(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $own = $this->createBusinessFor($user);

        $this->actingAs($user)
            ->withSession(['active_business_id' => 999999999])
            ->get('/__testing/current-business-contract')
            ->assertOk()
            ->assertJson(['business_id' => $own->getKey()])
            ->assertSessionHas('active_business_id', $own->getKey());
    }

    public function test_guest_is_redirected_to_login_and_user_without_business_is_sent_to_onboarding(): void
    {
        $this->get('/__testing/current-business-contract')->assertRedirect(route('login'));

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)
            ->get('/__testing/current-business-contract')
            ->assertRedirect(route('onboarding'));
    }

    public function test_manager_can_view_settings_but_direct_put_without_update_permission_is_forbidden(): void
    {
        $manager = User::factory()->create(['email_verified_at' => now()]);
        $business = $this->createBusinessFor($manager, BusinessRole::Manager);

        $this->actingAs($manager)->get(route('business.settings.edit'))->assertOk();
        $this->actingAs($manager)->put(route('business.settings.update'), [
            'name' => 'Forbidden Manager Update',
        ])->assertForbidden();

        $this->assertDatabaseMissing('businesses', ['id' => $business->getKey(), 'name' => 'Forbidden Manager Update']);
    }

    public function test_multi_membership_selects_the_lowest_business_id_deterministically_when_session_is_empty(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $first = $this->createBusinessFor($user);
        $second = $this->createBusinessFor($user);

        $this->actingAs($user)
            ->get('/__testing/current-business-contract')
            ->assertOk()
            ->assertJson(['business_id' => min($first->getKey(), $second->getKey())]);
    }

    public function test_current_business_context_does_not_leak_between_authenticated_and_guest_requests(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->createBusinessFor($user);

        $this->actingAs($user)
            ->get('/__testing/current-business-contract')
            ->assertOk();

        auth()->guard()->logout();

        $this->get('/__testing/current-business-guest-contract')
            ->assertRedirect(route('login'));

        $this->assertNull(app(CurrentBusiness::class)->get());
    }

    public function test_current_business_is_empty_in_a_fresh_synchronous_job_scope_unless_explicitly_set(): void
    {
        $business = Business::factory()->create();

        $this->app->forgetScopedInstances();
        $current = app(CurrentBusiness::class);

        $this->assertNull($current->get());

        $current->set($business);
        $this->assertSame($business->getKey(), $current->get()?->getKey());

        $this->app->forgetScopedInstances();
        $this->assertNull(app(CurrentBusiness::class)->get());
    }
}
