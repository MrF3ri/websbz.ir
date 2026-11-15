<?php

namespace Tests\Feature\Business;

use App\Actions\Business\CreateBusinessAction;
use App\Enums\BusinessRole;
use App\Models\User;
use App\Modules\Business\Models\Business;
use App\Modules\Business\Models\BusinessMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\InteractsWithBusinesses;
use Tests\TestCase;

class CreateBusinessActionTest extends TestCase
{
    use InteractsWithBusinesses;
    use RefreshDatabase;

    public function test_slug_collision_creates_a_business_with_a_unique_suffix(): void
    {
        $user = User::factory()->create();
        Business::factory()->create(['name' => 'Coffee House', 'slug' => 'coffee-house']);

        $business = app(CreateBusinessAction::class)->execute($user, ['name' => 'Coffee House']);

        $this->assertSame('coffee-house-2', $business->slug);
        $this->assertDatabaseHas('business_members', [
            'business_id' => $business->getKey(),
            'user_id' => $user->getKey(),
            'role' => BusinessRole::Owner->value,
        ]);
    }

    public function test_membership_failure_rolls_back_business_creation_without_an_orphan(): void
    {
        $user = User::factory()->create();

        BusinessMember::creating(function (): void {
            throw new RuntimeException('Simulated membership persistence failure.');
        });

        try {
            $this->expectException(RuntimeException::class);
            app(CreateBusinessAction::class)->execute($user, ['name' => 'Rollback Cafe']);
        } finally {
            BusinessMember::flushEventListeners();
            BusinessMember::boot();
        }

        $this->assertDatabaseMissing('businesses', ['slug' => 'rollback-cafe']);
        $this->assertDatabaseCount('businesses', 0);
    }
}
