<?php

namespace Tests\Feature;

use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\User;
use App\Services\AuthorizationService;
use Database\Seeders\OnpRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\AuthenticateSession;
use Tests\TestCase;

class Phase4BRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(AuthenticateSession::class);
    }

    public function test_role_negative_access_matrix_for_phase4b_business_routes(): void
    {
        $this->seed(OnpRolePermissionSeeder::class);
        $unit = OrganizationalUnit::query()->create(['name' => 'Demo Unit', 'unit_type' => 'service', 'is_active' => true]);
        $owner = $this->roleUser('po@example.test', 'PROCESS_OWNER', $unit->id);
        $kmt = $this->roleUser('kmt@example.test', 'KMT');
        $division = $this->roleUser('division@example.test', 'DIVISION_HEAD', $unit->id);
        $mcc = $this->roleUser('mcc@example.test', 'MCC');
        $rmic = $this->roleUser('rmic@example.test', 'RMIC');
        $admin = User::query()->create(['name' => 'Demo Admin', 'email' => 'admin@example.test', 'password' => 'password', 'is_active' => true, 'is_system_admin' => true]);
        $staff = User::query()->create(['name' => 'Demo Recipient', 'email' => 'staff@example.test', 'password' => 'password', 'is_active' => true]);

        $this->actingAs($owner)->getJson('/api/master-code-register')->assertForbidden();
        $this->actingAs($division)->getJson('/api/documents/code-components')->assertForbidden();
        $this->actingAs($mcc)->getJson('/api/documents/code-components')->assertForbidden();
        $this->actingAs($rmic)->getJson('/api/operational-records')->assertForbidden();
        $this->actingAs($admin)->getJson('/api/documents')->assertForbidden();
        $this->actingAs($admin)->getJson('/api/admin/access')->assertOk();
        $this->actingAs($staff)->getJson('/api/documents')->assertForbidden();
        $this->actingAs($staff)->getJson('/api/admin/access')->assertForbidden();

        $this->assertFalse(app(AuthorizationService::class)->allows($kmt, 'document.master_copy.approve.manual_sop_guideline'));
        $this->assertFalse(app(AuthorizationService::class)->allows($division, 'document.release.registered', $unit->id));
        $this->assertFalse(app(AuthorizationService::class)->allows($mcc, 'document.release.registered'));
        $this->assertFalse(app(AuthorizationService::class)->allows($admin, 'document.release.registered'));
    }

    public function test_combined_roles_do_not_expand_unit_scope(): void
    {
        $this->seed(OnpRolePermissionSeeder::class);
        $unitA = OrganizationalUnit::query()->create(['name' => 'Demo Unit A', 'unit_type' => 'service', 'is_active' => true]);
        $unitB = OrganizationalUnit::query()->create(['name' => 'Demo Unit B', 'unit_type' => 'service', 'is_active' => true]);
        $user = $this->roleUser('multi@example.test', 'PROCESS_OWNER', $unitA->id);
        $user->roleAssignments()->create([
            'role_id' => Role::query()->where('code', 'DIVISION_HEAD')->value('id'),
            'organizational_unit_id' => $unitA->id,
            'work_scope' => 'Second active role in same unit',
            'valid_from' => now()->toDateString(),
        ]);
        $this->actingAs($user)->getJson('/api/documents')->assertOk();
        $this->assertFalse(app(AuthorizationService::class)->allows($user, 'document.registry.view', $unitB->id));
    }

    private function roleUser(string $email, string $roleCode, ?int $unitId = null): User
    {
        $user = User::query()->create(['name' => $roleCode, 'email' => $email, 'password' => 'password', 'is_active' => true]);
        $role = Role::query()->where('code', $roleCode)->firstOrFail();
        $user->roleAssignments()->create([
            'role_id' => $role->id,
            'organizational_unit_id' => $unitId,
            'work_scope' => 'Fictional test assignment',
            'valid_from' => now()->toDateString(),
        ]);
        return $user;
    }
}
