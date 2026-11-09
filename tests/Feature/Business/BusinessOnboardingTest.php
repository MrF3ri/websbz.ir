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
                    ->component('Business/Create')
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
