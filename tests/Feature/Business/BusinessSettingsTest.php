<?php

namespace Tests\Feature\Business;

use App\Enums\BusinessRole;
use App\Models\User;
use App\Modules\Business\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BusinessSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_user_can_view_and_update_current_business_settings(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $business = $this->createBusinessFor($user, 'Current Business');

        $this->actingAs($user)
            ->get(route('business.settings.edit'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Business/Settings', false)
                    ->where('business.name', 'Current Business')
                    ->where('business.phone', null)
            );

        $response = $this->put(route('business.settings.update'), [
            'name' => 'Updated Business Name',
            'phone' => '+989121234567',
            'email' => 'contact@example.test',
            'address' => 'Valiasr Street, No. 10',
            'city' => 'Tehran',
            'postal_code' => '1234567890',
        ]);

        $response
            ->assertRedirect(route('business.settings.edit'))
            ->assertSessionHas(
                'success',
                'Business settings updated successfully.'
            );

        $this->assertDatabaseHas('businesses', [
            'id' => $business->id,
            'name' => 'Updated Business Name',
            'phone' => '+989121234567',
            'email' => 'contact@example.test',
            'address' => 'Valiasr Street, No. 10',
            'city' => 'Tehran',
            'postal_code' => '1234567890',
        ]);
    }

    public function test_user_cannot_select_or_update_another_users_business(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $otherUser = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $ownBusiness = $this->createBusinessFor($user, 'Own Business');
        $otherBusiness = $this->createBusinessFor($otherUser, 'Other Business');

        $this->actingAs($user)
            ->withSession([
                'active_business_id' => $otherBusiness->getKey(),
            ])
            ->put(route('business.settings.update'), [
                'name' => 'Updated Own Business',
                // A client-supplied ID must never select the record to update.
                'business_id' => $otherBusiness->getKey(),
                'phone' => '+989000000000',
                'city' => 'Attacker City',
            ])
            ->assertRedirect(route('business.settings.edit'));

        $this->assertDatabaseHas('businesses', [
            'id' => $ownBusiness->id,
            'name' => 'Updated Own Business',
            'phone' => '+989000000000',
            'city' => 'Attacker City',
        ]);

        $this->assertDatabaseHas('businesses', [
            'id' => $otherBusiness->id,
            'name' => 'Other Business',
            'phone' => null,
            'city' => null,
        ]);
    }

    public function test_manager_can_view_but_cannot_update_business_settings(): void
    {
        $manager = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $business = $this->createBusinessFor(
            $manager,
            'Managed Business',
            BusinessRole::Manager
        );

        $this->actingAs($manager)
            ->get(route('business.settings.edit'))
            ->assertOk();

        $this->actingAs($manager)
            ->put(route('business.settings.update'), [
                'name' => 'Unauthorized Rename',
                'phone' => '+989111111111',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('businesses', [
            'id' => $business->id,
            'name' => 'Managed Business',
            'phone' => null,
        ]);
    }

    private function createBusinessFor(
        User $user,
        string $name,
        BusinessRole $role = BusinessRole::Owner
    ): Business {
        $business = Business::query()->create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
        ]);

        $business->members()->create([
            'user_id' => $user->getKey(),
            'role' => $role,
        ]);

        return $business;
    }
}
