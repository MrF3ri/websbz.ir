<?php

namespace Tests\Feature;

use App\Actions\Business\CreateBusinessAction;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_with_a_business_can_visit_the_dashboard()
    {
        $user = User::factory()->create();

        app(CreateBusinessAction::class)->execute($user, [
            'name' => 'Test Business',
            'plan_code' => 'economic',
        ]);

        $this->actingAs($user);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
    }
}
