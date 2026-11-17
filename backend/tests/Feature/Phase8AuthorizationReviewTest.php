<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase8AuthorizationReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_protected_phase6_and_phase7_read_routes_reject_anonymous_and_admin_only_accounts(): void
    {
        $paths = [
            '/api/documents', '/api/distribution/inbox', '/api/distribution/target-options',
            '/api/document-migrations', '/api/external-references', '/api/operational-records',
            '/api/reports/summary', '/api/notifications',
        ];
        foreach ($paths as $path) $this->getJson($path)->assertUnauthorized();

        $admin = User::query()->create(['name' => 'Admin', 'email' => 'admin-only@example.test', 'password' => 'password', 'is_active' => true, 'is_system_admin' => true]);
        $this->actingAs($admin);
        foreach (array_diff($paths, ['/api/distribution/inbox', '/api/notifications']) as $path) $this->getJson($path)->assertForbidden();
        $this->getJson('/api/distribution/inbox')->assertOk()->assertJsonPath('data', []);
        $this->getJson('/api/notifications')->assertOk()->assertJsonPath('unread_count', 0);
    }

    public function test_unit_scoped_registry_never_lists_another_unit_document(): void
    {
        $user = User::query()->create(['name' => 'Unit owner', 'email' => 'unit-owner@example.test', 'password' => 'password', 'is_active' => true]);
        $unitA = OrganizationalUnit::query()->create(['name' => 'Unit A', 'unit_type' => 'service', 'is_active' => true]);
        $unitB = OrganizationalUnit::query()->create(['name' => 'Unit B', 'unit_type' => 'service', 'is_active' => true]);
        $role = Role::query()->create(['code' => 'UNIT_OWNER', 'name' => 'Unit Owner', 'is_active' => true]);
        $permission = Permission::query()->create(['code' => 'document.registry.view', 'description' => 'View assigned unit register.']);
        $role->permissions()->attach($permission->id, ['scope_type' => 'organizational_unit']);
        $user->roleAssignments()->create(['role_id' => $role->id, 'organizational_unit_id' => $unitA->id, 'work_scope' => 'Unit A', 'valid_from' => now()->toDateString()]);
        $type = DocumentType::query()->create(['code' => 'POL', 'name' => 'Policy', 'is_active' => true]);
        $visible = Document::query()->create(['document_type_id' => $type->id, 'owner_user_id' => $user->id, 'owning_unit_id' => $unitA->id, 'created_by_user_id' => $user->id, 'title' => 'Unit A document', 'current_revision_label' => '1.0', 'status' => 'pending_code']);
        Document::query()->create(['document_type_id' => $type->id, 'owner_user_id' => $user->id, 'owning_unit_id' => $unitB->id, 'created_by_user_id' => $user->id, 'title' => 'Unit B secret', 'current_revision_label' => '1.0', 'status' => 'pending_code']);

        $this->actingAs($user)->getJson('/api/documents')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id)
            ->assertDontSee('Unit B secret');
    }
}
