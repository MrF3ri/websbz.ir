<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PlatformAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_granted_platform_admin_can_open_admin_routes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.payments.index'))
            ->assertForbidden();

        $user->forceFill(['is_platform_admin' => true])->save();

        $this->actingAs($user)
            ->get(route('admin.payments.index'))
            ->assertOk();
    }

    public function test_grant_command_allows_only_one_platform_admin(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        Artisan::call('platform-admin:grant', ['email' => $first->email]);

        $this->assertTrue($first->fresh()->is_platform_admin);

        $exitCode = Artisan::call('platform-admin:grant', ['email' => $second->email]);

        $this->assertSame(1, $exitCode);
        $this->assertFalse($second->fresh()->is_platform_admin);
    }
}
