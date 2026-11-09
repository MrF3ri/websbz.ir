<?php

namespace Tests\Feature\Business;

use App\Enums\BusinessRole;
use App\Models\User;
use App\Modules\Business\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BusinessOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_authenticated_user_can_view_onboarding_page(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('onboarding'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Business/Create', false)
            );
    }

    public function test_authenticated_user_can_create_business(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->post('/onboarding', [
                'name' => 'F3ri Test Cafe',
            ]);

        $response
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas(
                'success',
                'Business created successfully.'
            );

        $business = Business::query()
            ->where('name', 'F3ri Test Cafe')
            ->firstOrFail();

        $this->assertDatabaseHas('businesses', [
            'id' => $business->id,
            'name' => 'F3ri Test Cafe',
            'slug' => 'f3ri-test-cafe',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('business_members', [
            'business_id' => $business->id,
            'user_id' => $user->id,
            'role' => BusinessRole::Owner->value,
        ]);
    }

    public function test_user_with_business_is_redirected_from_onboarding(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $business = Business::query()->create([
            'name' => 'Existing Business',
            'slug' => 'existing-business',
        ]);

        $business->members()->create([
            'user_id' => $user->getKey(),
            'role' => BusinessRole::Owner,
        ]);

        $this->actingAs($user)
            ->get(route('onboarding'))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($user)
            ->post(route('onboarding'), [
                'name' => 'Duplicate Business',
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseCount('businesses', 1);
        $this->assertDatabaseCount('business_members', 1);
        $this->assertDatabaseMissing('businesses', [
            'name' => 'Duplicate Business',
        ]);
    }

    public function test_business_name_is_required(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->post('/onboarding', [
                'name' => '',
            ])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('businesses', 0);
        $this->assertDatabaseCount('business_members', 0);
    }
}
