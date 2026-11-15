<?php

namespace Tests\Feature\Business;

use App\Modules\Business\Models\Business;
use App\Modules\Subscription\Actions\StartTrialAction;
use App\Modules\Subscription\Models\Plan;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Console\Command\Command;
use Tests\TestCase;

class StartBusinessTrialCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_command_starts_a_trial_for_an_existing_business_without_a_subscription(): void
    {
        $business = Business::factory()->create(['slug' => 'legacy-cafe']);

        $this->artisan('subscription:start-trial', [
            'business-slug' => $business->slug,
            'plan-code' => 'economic',
        ])->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseHas('subscriptions', [
            'business_id' => $business->getKey(),
            'status' => 'trialing',
        ]);
    }

    public function test_command_refuses_to_change_a_business_that_already_has_a_subscription(): void
    {
        $business = Business::factory()->create(['slug' => 'subscribed-cafe']);
        app(StartTrialAction::class)->execute(
            $business,
            Plan::query()->where('code', 'economic')->firstOrFail(),
        );

        $this->artisan('subscription:start-trial', [
            'business-slug' => $business->slug,
            'plan-code' => 'professional',
        ])->assertExitCode(Command::FAILURE);

        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseHas('subscriptions', [
            'business_id' => $business->getKey(),
            'plan_id' => Plan::query()->where('code', 'economic')->value('id'),
        ]);
    }
}
