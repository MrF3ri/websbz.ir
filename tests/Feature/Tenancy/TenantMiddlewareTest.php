<?php

namespace Tests\Feature\Tenancy;

use App\Enums\BusinessRole;
use App\Models\User;
use App\Modules\Business\Models\Business;
use App\Modules\Tenancy\Http\Middleware\TenantMiddleware;
use App\Modules\Tenancy\Services\CurrentBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware([
            'web',
            'auth',
            'verified',
            TenantMiddleware::class,
        ])->get('/__testing/tenant-context', function (CurrentBusiness $currentBusiness) {
            return response()->json([
                'has_business' => $currentBusiness->has(),
                'business_id' => $currentBusiness->get()?->getKey(),
                'public_id' => $currentBusiness->get()?->public_id,
            ]);
        });
    }

    public function test_user_without_a_business_is_redirected_to_onboarding(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/__testing/tenant-context')
            ->assertRedirect(route('onboarding'));
    }

    public function test_user_business_is_set_as_current_and_saved_in_session(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $business = $this->createBusinessFor($user);

        $this->actingAs($user)
            ->get('/__testing/tenant-context')
            ->assertOk()
            ->assertJson([
                'has_business' => true,
                'business_id' => $business->getKey(),
                'public_id' => $business->public_id,
            ])
            ->assertSessionHas('active_business_id', $business->getKey());
    }

    public function test_foreign_tenant_session_id_falls_back_to_users_own_business(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $anotherUser = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $usersBusiness = $this->createBusinessFor($user);
        $anotherUsersBusiness = $this->createBusinessFor($anotherUser);

        $this->actingAs($user)
            ->withSession([
                'active_business_id' => $anotherUsersBusiness->getKey(),
            ])
            ->get('/__testing/tenant-context')
            ->assertOk()
            ->assertJson([
                'has_business' => true,
                'business_id' => $usersBusiness->getKey(),
                'public_id' => $usersBusiness->public_id,
            ])
            ->assertSessionHas('active_business_id', $usersBusiness->getKey());
    }

    private function createBusinessFor(User $user): Business
    {
        $business = Business::query()->create([
            'name' => 'Test Business '.Str::uuid(),
            'slug' => 'test-business-'.Str::uuid(),
        ]);

        $business->users()->attach($user->getKey(), [
            'role' => BusinessRole::Owner->value,
        ]);

        return $business;
    }
}
