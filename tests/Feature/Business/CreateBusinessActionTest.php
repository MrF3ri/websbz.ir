<?php

namespace Tests\Feature\Business;

use App\Actions\Business\CreateBusinessAction;
use App\Enums\BusinessRole;
use App\Models\User;
use App\Modules\Business\Models\Business;
use App\Modules\Business\Models\BusinessMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
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
        $event = 'eloquent.creating: '.BusinessMember::class;

        Event::listen($event, function (): void {
            throw new RuntimeException('Simulated membership persistence failure.');
        });

        try {
            app(CreateBusinessAction::class)->execute($user, ['name' => 'Rollback Cafe']);
            $this->fail('The simulated membership failure should be thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated membership persistence failure.', $exception->getMessage());
        } finally {
            Event::forget($event);
        }

        $this->assertDatabaseMissing('businesses', ['slug' => 'rollback-cafe']);
        $this->assertDatabaseCount('businesses', 0);
    }
}
