<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithBusinesses;
use Tests\TestCase;

class UserPublicIdTest extends TestCase
{
    use InteractsWithBusinesses;
    use RefreshDatabase;

    public function test_user_public_id_is_generated_as_a_26_character_ulid(): void
    {
        $user = User::factory()->create();

        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $user->public_id);
        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'public_id' => $user->public_id,
        ]);
    }

    public function test_user_public_id_is_not_mass_assignable(): void
    {
        $user = User::query()->create([
            'name' => 'Mass Assignment Test',
            'email' => 'mass-assignment@example.test',
            'password' => 'password',
            'public_id' => '01H00000000000000000000000',
        ]);

        $this->assertNotSame('01H00000000000000000000000', $user->public_id);
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $user->public_id);
    }

    public function test_user_public_id_is_not_exposed_in_authenticated_inertia_props(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->createBusinessFor($user);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->missing('auth.user.public_id'));
    }
}
