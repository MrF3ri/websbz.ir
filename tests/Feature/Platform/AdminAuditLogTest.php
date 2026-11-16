<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Modules\Platform\Models\PlatformAuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_settings_changes_are_audited_with_actor_and_redacted_card_number(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_platform_admin' => true])->save();

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), [
                'bank_name' => 'ملت',
                'card_number' => '1234567812345678',
                'card_holder' => 'Platform Owner',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $log = PlatformAuditLog::query()->sole();

        $this->assertSame($admin->getKey(), $log->actor_id);
        $this->assertSame('platform.settings.card_details.updated', $log->action);
        $this->assertSame('platform_settings', $log->target_type);
        $this->assertSame('card_details', $log->target_id);
        $this->assertSame('5678', $log->after_data['card_number_last4']);
        $this->assertArrayNotHasKey('card_number', $log->after_data);
        $this->assertStringNotContainsString('1234567812345678', json_encode($log->toArray(), JSON_THROW_ON_ERROR));

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/AuditLogs/Index')
                ->where('logs.total', 1)
                ->where('logs.data.0.action', 'platform.settings.card_details.updated')
                ->where('logs.data.0.actor_email', $admin->email)
                ->where('logs.data.0.after.card_number_last4', '5678')
                ->missing('logs.data.0.after.card_number'));
    }

    public function test_unchanged_platform_settings_do_not_create_duplicate_audit_entries(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_platform_admin' => true])->save();

        $payload = [
            'bank_name' => 'ملت',
            'card_number' => '1234567812345678',
            'card_holder' => 'Platform Owner',
        ];

        $this->actingAs($admin)->put(route('admin.settings.update'), $payload)->assertRedirect();
        $this->actingAs($admin)->put(route('admin.settings.update'), $payload)->assertRedirect();

        $this->assertSame(1, PlatformAuditLog::query()->count());
    }

    public function test_audit_records_cannot_be_updated_or_deleted_through_the_model(): void
    {
        $log = PlatformAuditLog::query()->create([
            'action' => 'test.immutable',
            'target_type' => 'test',
            'target_id' => '1',
        ]);

        try {
            $log->update(['action' => 'changed']);
            $this->fail('Audit records must reject updates.');
        } catch (\LogicException) {
            $this->assertSame('test.immutable', $log->fresh()->action);
        }

        try {
            $log->delete();
            $this->fail('Audit records must reject deletion.');
        } catch (\LogicException) {
            $this->assertDatabaseHas('platform_audit_logs', ['id' => $log->getKey()]);
        }
    }

    public function test_non_platform_admin_cannot_view_audit_log(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.audit-logs.index'))
            ->assertForbidden();
    }
}
