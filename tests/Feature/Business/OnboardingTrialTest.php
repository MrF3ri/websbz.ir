<?php

namespace Tests\Feature\Business;

use App\Models\User;
use App\Modules\Business\Models\Business;
use App\Modules\Subscription\Actions\StartTrialAction;
use App\Modules\Subscription\Models\Plan;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class OnboardingTrialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PlanSeeder::class);
    }

    public function test_onboarding_offers_all_active_plans_for_owner_selection(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('onboarding'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('plans.0.code', 'economic')
                ->where('plans.1.code', 'professional')
                ->where('plans.2.code', 'top'));
    }

    public function test_selected_plan_starts_a_72_hour_trial_with_its_own_snapshot(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->post(route('onboarding.store'), [
                'name' => 'Professional Cafe',
                'plan_code' => 'professional',
            ])
            ->assertRedirect(route('dashboard'));

        $business = Business::query()->where('slug', 'professional-cafe')->firstOrFail();
        $subscription = $business->subscriptions()->with('plan')->firstOrFail();

        $this->assertSame('professional', $subscription->plan->code);
        $this->assertSame('trialing', $subscription->status->value);
        $this->assertSame(
            72,
            (int) $subscription->current_period_starts_at?->diffInHours($subscription->trial_ends_at),
        );
        $this->assertSame(-1, $subscription->features_snapshot['max_products']);
        $this->assertTrue($subscription->features_snapshot['product_discount']);
    }

    public function test_invalid_or_inactive_plan_code_fails_validation_without_creating_records(): void
    {
        Plan::query()
            ->where('code', 'economic')
            ->update(['is_active' => false]);

        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->post(route('onboarding.store'), [
                'name' => 'Invalid Plan Cafe',
                'plan_code' => 'economic',
            ])
            ->assertSessionHasErrors('plan_code');

        $this->assertDatabaseCount('businesses', 0);
        $this->assertDatabaseCount('business_members', 0);
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_trial_start_failure_rolls_back_business_membership_and_subscription(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $trialAction = Mockery::mock(StartTrialAction::class);
        $trialAction->shouldReceive('execute')
            ->once()
            ->andThrow(new RuntimeException('Trial creation failed.'));
        $this->app->instance(StartTrialAction::class, $trialAction);

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($user)->post(route('onboarding.store'), [
                'name' => 'Rollback Trial Cafe',
                'plan_code' => 'economic',
            ]);
            $this->fail('The trial action failure should be propagated.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Trial creation failed.', $exception->getMessage());
        }

        $this->assertDatabaseCount('businesses', 0);
        $this->assertDatabaseCount('business_members', 0);
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseCount('menus', 0);
    }
}
