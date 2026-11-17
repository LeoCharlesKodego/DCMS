<?php

namespace Tests\Feature;

use App\Models\DistributionRecipient;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\DocumentDistributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DocumentDistributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_distribution_inbox_requires_authentication(): void
    {
        $this->getJson('/api/distribution/inbox')->assertUnauthorized();
    }

    public function test_kmt_can_distribute_a_released_version_and_only_the_recipient_can_acknowledge(): void
    {
        [$issuer, $recipient, $version] = $this->distributionFixture();
        $distribution = app(DocumentDistributionService::class)->issue($version, $issuer, [
            'targets' => [['target_type' => 'user', 'target_id' => $recipient->id]],
            'acknowledgment_required' => true,
            'due_at' => now()->addDay()->toDateString(),
        ]);
        $recipientRow = $distribution->recipients->first();

        $this->assertNotNull($recipientRow);
        $this->assertSame('pending', $recipientRow->acknowledgment_status);
        $this->assertSame($version->id, $distribution->document_version_id);
        $this->assertTrue(app(DocumentDistributionService::class)->canAccessVersion($recipient, (int) $version->id));
        $this->assertFalse(app(DocumentDistributionService::class)->canAccessVersion($issuer, (int) $version->id));
        $this->assertCount(1, app(DocumentDistributionService::class)->inboxFor($recipient));
        $this->assertCount(0, app(DocumentDistributionService::class)->inboxFor($issuer));
        $this->assertDatabaseHas('user_notifications', ['user_id' => $recipient->id, 'event_type' => 'distribution.received', 'target_id' => $recipientRow->id]);
        $this->actingAs($recipient)->getJson('/api/notifications')->assertOk()->assertJsonPath('unread_count', 1);
        $this->assertDatabaseHas('audit_events', [
            'target_type' => 'document_distribution',
            'target_id' => $distribution->id,
            'event_type' => 'distribution.issued',
        ]);

        try {
            app(DocumentDistributionService::class)->acknowledge($recipientRow, $issuer);
            $this->fail('The issuer must not acknowledge on the recipient’s behalf.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        app(DocumentDistributionService::class)->acknowledge($recipientRow, $recipient);

        $this->assertSame('acknowledged', $recipientRow->fresh()->acknowledgment_status);
        $this->assertSame($recipient->id, $recipientRow->fresh()->acknowledged_by_user_id);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'distribution.acknowledged', 'document_id' => $version->document_id]);
    }

    public function test_unreleased_versions_and_accounts_without_kmt_authority_cannot_be_distributed(): void
    {
        [$issuer, , $version] = $this->distributionFixture();
        $version->update(['status' => 'approved']);
        $data = [
            'targets' => [['target_type' => 'user', 'target_id' => $issuer->id]],
            'acknowledgment_required' => false,
        ];

        try {
            app(DocumentDistributionService::class)->issue($version, $issuer, $data);
            $this->fail('An approved but unreleased version must not be distributed.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        $version->update(['status' => 'released']);
        $unauthorized = $this->user('unauthorized@example.test');
        $unauthorized->update(['is_system_admin' => true]);
        try {
            app(DocumentDistributionService::class)->issue($version, $unauthorized, $data);
            $this->fail('An account without the KMT distribution permission must not issue a distribution.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertDatabaseCount('document_distributions', 0);
    }

    public function test_kmt_can_exempt_one_pending_recipient_with_a_reason(): void
    {
        [$issuer, $recipient, $version] = $this->distributionFixture();
        $distribution = app(DocumentDistributionService::class)->issue($version, $issuer, [
            'targets' => [['target_type' => 'user', 'target_id' => $recipient->id]],
            'acknowledgment_required' => true,
        ]);
        $row = $distribution->recipients->firstOrFail();

        try {
            app(DocumentDistributionService::class)->exempt($row, $recipient, 'Not authorized');
            $this->fail('A recipient without KMT authority must not exempt acknowledgment.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $exempted = app(DocumentDistributionService::class)->exempt($row, $issuer, 'Recipient is on approved leave.');
        $this->assertSame('exempted', $exempted->acknowledgment_status);
        $this->assertSame($issuer->id, $exempted->exempted_by_user_id);
        $this->assertSame('Recipient is on approved leave.', $exempted->exemption_reason);
        $this->assertDatabaseHas('audit_events', [
            'target_type' => 'distribution_recipient',
            'target_id' => $row->id,
            'event_type' => 'distribution.exempted',
            'from_state' => 'pending',
            'to_state' => 'exempted',
            'reason' => 'Recipient is on approved leave.',
        ]);

        try {
            app(DocumentDistributionService::class)->exempt($row, $issuer, 'Another reason.');
            $this->fail('An already exempted recipient must not be exempted again.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
    }

    public function test_unit_and_role_targets_snapshot_one_recipient_once_when_targets_overlap(): void
    {
        [$issuer, $recipient, $version] = $this->distributionFixture();
        $unitId = (int) $version->document->owning_unit_id;
        $role = Role::query()->create(['code' => 'PROCESS_OWNER', 'name' => 'Process Owner', 'is_active' => true]);
        $recipient->roleAssignments()->create([
            'role_id' => $role->id,
            'organizational_unit_id' => $unitId,
            'work_scope' => 'Unit distribution target',
            'valid_from' => now()->toDateString(),
        ]);

        $distribution = app(DocumentDistributionService::class)->issue($version, $issuer, [
            'targets' => [
                ['target_type' => 'organizational_unit', 'target_id' => $unitId],
                ['target_type' => 'role', 'target_id' => $role->id],
                ['target_type' => 'user', 'target_id' => $recipient->id],
            ],
            'acknowledgment_required' => false,
        ]);

        $this->assertCount(3, $distribution->targets);
        $this->assertCount(1, $distribution->recipients);
        $this->assertSame('not_required', $distribution->recipients->first()->acknowledgment_status);
        $this->assertDatabaseCount('distribution_recipient_sources', 3);
    }

    /** @return array{User, User, \App\Models\DocumentVersion} */
    private function distributionFixture(): array
    {
        $issuer = $this->user('kmt@example.test');
        $recipient = $this->user('recipient@example.test');
        $unit = OrganizationalUnit::query()->create(['name' => 'Clinical Unit', 'unit_type' => 'service', 'is_active' => true]);
        $documentType = DocumentType::query()->create(['code' => 'PROC', 'name' => 'Procedure', 'is_active' => true]);
        $role = Role::query()->create(['code' => 'KMT', 'name' => 'Knowledge Management Team', 'is_active' => true]);
        $permission = Permission::query()->create(['code' => 'document.distribute', 'description' => 'KMT distributes released versions.']);
        $role->permissions()->attach($permission->id, ['scope_type' => 'institution']);
        $issuer->roleAssignments()->create([
            'role_id' => $role->id,
            'work_scope' => 'Institution-wide distribution',
            'valid_from' => now()->toDateString(),
        ]);
        $document = Document::query()->create([
            'document_type_id' => $documentType->id,
            'owner_user_id' => $issuer->id,
            'owning_unit_id' => $unit->id,
            'created_by_user_id' => $issuer->id,
            'title' => 'Distribution test document',
            'current_revision_label' => '1.0',
            'status' => 'active',
        ]);
        $version = $document->versions()->create([
            'sequence_no' => 1,
            'revision_label' => '1.0',
            'status' => 'released',
            'created_by_user_id' => $issuer->id,
        ]);

        return [$issuer, $recipient, $version];
    }

    private function user(string $email): User
    {
        return User::query()->create(['name' => $email, 'email' => $email, 'password' => 'password', 'is_active' => true]);
    }
}
