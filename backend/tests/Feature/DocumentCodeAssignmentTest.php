<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentCodeComponent;
use App\Models\DocumentType;
use App\Models\Institution;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\OnpRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\AuthenticateSession;
use Tests\TestCase;

/**
 * Regression cover for a82a6a1, part one: assign() referenced $audit inside the
 * transaction closure without capturing it, so every assign-code request failed.
 */
class DocumentCodeAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(AuthenticateSession::class);
    }

    public function test_kmt_assigns_a_code_and_the_audit_event_records_it(): void
    {
        $this->seed(OnpRolePermissionSeeder::class);
        Institution::query()->create(['name' => 'Ospital ng Palawan', 'short_name' => 'ONP', 'timezone' => 'Asia/Manila']);
        $unit = OrganizationalUnit::query()->create(['name' => 'Coding Unit', 'unit_type' => 'service', 'is_active' => true]);
        $kmt = $this->user('code-kmt@example.test', 'KMT');
        $document = $this->pendingCodeDocument($unit);
        $service = $this->codeComponent('service', 'TRN');
        $section = $this->codeComponent('section_unit', 'OPS', $service->id);
        $type = $this->codeComponent('document_type', 'PROC');

        $this->actingAs($kmt)->postJson("/api/documents/{$document->id}/assign-code", [
            'service_component_id' => $service->id,
            'section_component_id' => $section->id,
            'document_type_component_id' => $type->id,
        ])->assertOk()
            ->assertJsonPath('master_code_register_entry.base_code', 'ONP-TRN-OPS-PROC-001');

        $this->assertDatabaseHas('master_code_register_entries', [
            'document_id' => $document->id,
            'base_code' => 'ONP-TRN-OPS-PROC-001',
            'assigned_by_user_id' => $kmt->id,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'document_id' => $document->id,
            'event_type' => 'code.assigned',
            'from_state' => 'pending_code',
            'to_state' => 'registered',
        ]);
        $this->assertSame('registered', $document->fresh()->status);
    }

    public function test_an_account_without_the_code_assignment_permission_is_rejected(): void
    {
        $this->seed(OnpRolePermissionSeeder::class);
        Institution::query()->create(['name' => 'Ospital ng Palawan', 'short_name' => 'ONP', 'timezone' => 'Asia/Manila']);
        $unit = OrganizationalUnit::query()->create(['name' => 'Coding Unit', 'unit_type' => 'service', 'is_active' => true]);
        $owner = $this->user('code-owner@example.test', 'PROCESS_OWNER', $unit->id);
        $document = $this->pendingCodeDocument($unit);
        $service = $this->codeComponent('service', 'TRN');
        $section = $this->codeComponent('section_unit', 'OPS', $service->id);
        $type = $this->codeComponent('document_type', 'PROC');

        $this->actingAs($owner)->postJson("/api/documents/{$document->id}/assign-code", [
            'service_component_id' => $service->id,
            'section_component_id' => $section->id,
            'document_type_component_id' => $type->id,
        ])->assertForbidden();

        $this->assertDatabaseCount('master_code_register_entries', 0);
    }

    private function pendingCodeDocument(OrganizationalUnit $unit): Document
    {
        $documentType = DocumentType::query()->create(['code' => 'PROC', 'name' => 'Procedure', 'is_active' => true]);
        $owner = User::query()->create([
            'name' => 'Process Owner',
            'email' => 'code-document-owner@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);

        return Document::query()->create([
            'document_type_id' => $documentType->id,
            'owner_user_id' => $owner->id,
            'owning_unit_id' => $unit->id,
            'created_by_user_id' => $owner->id,
            'title' => 'Code assignment regression procedure',
            'current_revision_label' => '1.0',
            'status' => 'pending_code',
        ]);
    }

    private function codeComponent(string $type, string $code, ?int $parentId = null): DocumentCodeComponent
    {
        return DocumentCodeComponent::query()->create([
            'source_key' => $type.'-'.$code,
            'parent_service_id' => $parentId,
            'component_type' => $type,
            'code' => $code,
            'name' => $code,
            'source_group' => 'Regression fixture',
            'is_conflicted' => false,
            'is_active' => true,
        ]);
    }

    private function user(string $email, string $roleCode, ?int $unitId = null): User
    {
        $user = User::query()->create([
            'name' => $roleCode,
            'email' => $email,
            'password' => 'password',
            'is_active' => true,
        ]);
        $user->roleAssignments()->create([
            'role_id' => Role::query()->where('code', $roleCode)->value('id'),
            'organizational_unit_id' => $unitId,
            'work_scope' => 'Regression cover for code assignment',
            'valid_from' => now()->toDateString(),
        ]);

        return $user;
    }
}
