<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\OnpRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\AuthenticateSession;
use Tests\TestCase;

class Phase4BSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(AuthenticateSession::class);
    }

    public function test_kmt_title_default_and_admin_same_account_setting_are_versioned_and_audited(): void
    {
        $this->seed(OnpRolePermissionSeeder::class);
        $admin = User::query()->create(['name' => 'Local Admin', 'email' => 'admin-settings@example.test', 'password' => 'password', 'is_active' => true, 'is_system_admin' => true]);
        $kmt = User::query()->create(['name' => 'Local KMT', 'email' => 'kmt-settings@example.test', 'password' => 'password', 'is_active' => true]);
        $kmtRole = Role::query()->where('code', 'KMT')->firstOrFail();
        $kmt->roleAssignments()->create(['role_id' => $kmtRole->id, 'organizational_unit_id' => null, 'work_scope' => 'Test', 'valid_from' => now()->toDateString()]);

        $this->actingAs($admin)->getJson('/api/configuration/phase4b-settings')->assertOk()
            ->assertJsonPath('same_kmt_verify_release', false)->assertJsonPath('title_size_pt', 14);
        $this->actingAs($admin)->putJson('/api/admin/settings/same-kmt-verify-release', ['enabled' => true])->assertOk()
            ->assertJsonPath('same_kmt_verify_release', true);
        $this->assertDatabaseHas('phase4b_setting_history', ['key' => 'same_kmt_verify_release', 'old_value' => null, 'new_value' => '1', 'changed_by_user_id' => $admin->id]);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'configuration.phase4b_setting_changed', 'actor_user_id' => $admin->id]);

        $this->actingAs($kmt)->putJson('/api/configuration/phase4b-title-size', ['title_size_pt' => 16])->assertOk()
            ->assertJsonPath('title_size_pt', 16);
        $this->assertDatabaseHas('phase4b_setting_history', ['key' => 'title_size_pt', 'new_value' => '16', 'changed_by_user_id' => $kmt->id]);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'configuration.phase4b_setting_changed', 'actor_user_id' => $kmt->id]);

        $unprivileged = User::query()->create(['name' => 'Local Staff', 'email' => 'staff-settings@example.test', 'password' => 'password', 'is_active' => true]);
        $this->actingAs($unprivileged)->getJson('/api/configuration/phase4b-settings')->assertForbidden();
        $this->actingAs($unprivileged)->putJson('/api/configuration/phase4b-title-size', ['title_size_pt' => 18])->assertForbidden();
    }
}
