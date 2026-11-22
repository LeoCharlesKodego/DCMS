<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentDistribution;
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
 * Regression cover for 9d476b6 (visibleFor closures lost $viewer/$canManage).
 *
 * The failing closure only ran when a version had at least one distribution with a
 * recipient, so every fixture here carries one. Without it GET /api/documents returns
 * 200 even while the defect is present and the test proves nothing.
 */
class DocumentRegistryAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(AuthenticateSession::class);
    }

    public function test_document_register_returns_200_for_every_seeded_role_that_grants_registry_access(): void
    {
        $this->seed(OnpRolePermissionSeeder::class);
        $unit = OrganizationalUnit::query()->create(['name' => 'Registry Unit', 'unit_type' => 'service', 'is_active' => true]);
        $document = $this->releasedDocumentWithDistribution($unit);

        $institutionWide = ['MCC', 'QMR', 'KMT'];
        $unitScoped = ['PROCESS_OWNER', 'DIVISION_HEAD', 'DEPARTMENT_HEAD', 'SERVICE_CHIEF'];

        foreach ($institutionWide as $roleCode) {
            $user = $this->userForRole($roleCode, $roleCode.' registry viewer');
            $this->actingAs($user)->getJson('/api/documents')
                ->assertOk()
                ->assertJsonPath('data.0.id', $document->id);
        }

        foreach ($unitScoped as $roleCode) {
            $user = $this->userForRole($roleCode, $roleCode.' registry viewer', $unit->id);
            $this->actingAs($user)->getJson('/api/documents')
                ->assertOk()
                ->assertJsonPath('data.0.id', $document->id);
        }
    }

    public function test_document_register_returns_200_for_a_system_administrator_holding_a_document_control_assignment(): void
    {
        $this->seed(OnpRolePermissionSeeder::class);
        $unit = OrganizationalUnit::query()->create(['name' => 'Administrator Unit', 'unit_type' => 'service', 'is_active' => true]);
        $this->releasedDocumentWithDistribution($unit);

        $administrator = $this->userForRole('KMT', 'Local Test Administrator');
        $administrator->update(['is_system_admin' => true]);

        $this->actingAs($administrator)->getJson('/api/documents')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.versions.0.distributions.0.recipients.0.status', 'overdue');
    }

    public function test_document_register_keeps_forbidding_accounts_without_a_document_control_role(): void
    {
        $this->seed(OnpRolePermissionSeeder::class);
        $unit = OrganizationalUnit::query()->create(['name' => 'Unassigned Unit', 'unit_type' => 'service', 'is_active' => true]);
        $this->releasedDocumentWithDistribution($unit);

        $systemAdministrator = User::query()->create([
            'name' => 'Technical Administrator',
            'email' => 'technical-administrator@example.test',
            'password' => 'password',
            'is_active' => true,
            'is_system_admin' => true,
        ]);
        $ordinaryUser = User::query()->create([
            'name' => 'Ordinary User',
            'email' => 'ordinary-user@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);

        $this->actingAs($systemAdministrator)->getJson('/api/documents')->assertForbidden();
        $this->actingAs($ordinaryUser)->getJson('/api/documents')->assertForbidden();
    }

    private function releasedDocumentWithDistribution(OrganizationalUnit $unit): Document
    {
        Institution::query()->create(['name' => 'Ospital ng Palawan', 'short_name' => 'ONP', 'timezone' => 'Asia/Manila']);
        $kmt = $this->userForRole('KMT', 'Registry KMT');
        $recipient = User::query()->create([
            'name' => 'Distribution Recipient',
            'email' => 'distribution-recipient@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $documentType = DocumentType::query()->create(['code' => 'PROC', 'name' => 'Procedure', 'is_active' => true]);
        $document = Document::query()->create([
            'document_type_id' => $documentType->id,
            'owner_user_id' => $kmt->id,
            'owning_unit_id' => $unit->id,
            'created_by_user_id' => $kmt->id,
            'title' => 'Register access regression procedure',
            'current_revision_label' => '1.0',
            'status' => 'active',
        ]);
        $version = $document->versions()->create([
            'sequence_no' => 1,
            'revision_label' => '1.0',
            'status' => 'released',
            'created_by_user_id' => $kmt->id,
        ]);
        $distribution = DocumentDistribution::query()->create([
            'document_version_id' => $version->id,
            'issued_by_user_id' => $kmt->id,
            'issued_at' => now(),
        ]);
        $distribution->recipients()->create([
            'user_id' => $recipient->id,
            'recipient_name_at_issue' => $recipient->name,
            'acknowledgment_required' => true,
            'due_at' => now()->subDay()->toDateString(),
            'acknowledgment_status' => 'pending',
        ]);

        return $document;
    }

    private function userForRole(string $roleCode, string $name, ?int $unitId = null): User
    {
        $user = User::query()->create([
            'name' => $name,
            'email' => str($roleCode.'-'.str()->uuid())->lower()->replace(' ', '-').'@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $user->roleAssignments()->create([
            'role_id' => Role::query()->where('code', $roleCode)->value('id'),
            'organizational_unit_id' => $unitId,
            'work_scope' => 'Regression cover for the register read route',
            'valid_from' => now()->toDateString(),
        ]);

        return $user;
    }
}
