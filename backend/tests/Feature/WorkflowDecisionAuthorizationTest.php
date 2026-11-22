<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentCodeComponent;
use App\Models\DocumentType;
use App\Models\Institution;
use App\Models\OrganizationalUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowDecision;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStageInstance;
use App\Models\WorkflowTask;
use App\Models\WorkflowTemplate;
use App\Services\WorkflowDecisionService;
use App\Services\WorkflowInstanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\AuthenticateSession;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class WorkflowDecisionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The suite signs in several actors per test; the session guard would otherwise
        // keep the first actor's identity for later requests.
        $this->withoutMiddleware(AuthenticateSession::class);
    }

    public function test_workflow_decision_endpoint_requires_authentication(): void
    {
        $this->postJson('/api/workflow-tasks/1/decisions', ['decision_type' => 'reviewed'])
            ->assertUnauthorized();
    }

    public function test_review_and_final_approval_require_separate_authorized_users_and_keep_release_separate(): void
    {
        [$owner, $reviewer, $approver, $version, $instance, $reviewTask, $approvalTask] = $this->workflowFixture();
        $service = app(WorkflowDecisionService::class);

        $service->decide($reviewTask, $reviewer, 'reviewed');

        $this->assertSame('ready_for_approval', $version->fresh()->status);
        $this->assertSame('active', $approvalTask->stageInstance->fresh()->status);
        try {
            $service->decide($approvalTask, $reviewer, 'approved');
            $this->fail('The same reviewer must not perform the final approval.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $service->decide($approvalTask, $approver, 'approved');

        $this->assertSame('approved', $version->fresh()->status);
        $this->assertSame('completed', $instance->fresh()->status);
        $this->assertSame(2, WorkflowDecision::query()->count());
        $this->assertDatabaseHas('audit_events', ['event_type' => 'workflow.approved', 'document_id' => $version->document_id]);
        $this->assertNotSame('released', $version->fresh()->status);
    }

    public function test_actor_without_the_task_scope_cannot_record_a_decision(): void
    {
        [, , , $version, , $reviewTask] = $this->workflowFixture();
        $outsideReviewer = $this->user('outside-reviewer@example.test');
        $outsideUnit = OrganizationalUnit::query()->create(['name' => 'Other Unit', 'unit_type' => 'service', 'is_active' => true]);
        $qmr = Role::query()->where('code', 'QMR')->firstOrFail();
        $reviewPermission = Permission::query()->where('code', 'document.review.verify')->firstOrFail();
        $qmr->permissions()->attach($reviewPermission->id, ['scope_type' => 'organizational_unit']);
        $reviewTask->update(['organizational_unit_id' => $version->document->owning_unit_id]);
        $outsideReviewer->roleAssignments()->create([
            'role_id' => $qmr->id,
            'organizational_unit_id' => $outsideUnit->id,
            'work_scope' => 'Other unit review',
            'valid_from' => now()->toDateString(),
        ]);

        try {
            app(WorkflowDecisionService::class)->decide($reviewTask, $outsideReviewer, 'reviewed');
            $this->fail('A reviewer from another unit must not act on this task.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertDatabaseCount('workflow_decisions', 0);
    }

    public function test_return_preserves_decision_and_resubmission_creates_a_new_pass(): void
    {
        [$owner, $reviewer, , $version, $instance, $reviewTask] = $this->workflowFixture();

        app(WorkflowDecisionService::class)->decide($reviewTask, $reviewer, 'returned_for_revision', 'Please correct section 2.');

        $this->assertSame('returned', $instance->fresh()->status);
        $this->assertSame('returned_for_revision', $version->fresh()->status);
        $version->update(['status' => 'submitted']);
        app(WorkflowInstanceService::class)->createForSubmission($version, $owner);

        $this->assertSame('in_progress', $instance->fresh()->status);
        $this->assertSame(2, $instance->stages()->max('pass_no'));
        $this->assertDatabaseCount('workflow_decisions', 1);
        $this->assertDatabaseHas('workflow_stage_instances', [
            'workflow_instance_id' => $instance->id,
            'pass_no' => 2,
            'status' => 'active',
        ]);
    }

    /** @return array{User, User, User, \App\Models\DocumentVersion, WorkflowInstance, WorkflowTask, WorkflowTask} */
    private function workflowFixture(): array
    {
        $owner = $this->user('owner@example.test');
        $reviewer = $this->user('reviewer@example.test');
        $approver = $this->user('approver@example.test');
        $kmtUser = $this->user('kmt@example.test');
        $unit = OrganizationalUnit::query()->create(['name' => 'Clinical Unit', 'unit_type' => 'service', 'is_active' => true]);
        $documentType = DocumentType::query()->create(['code' => 'MANL', 'name' => 'Manual', 'is_active' => true]);

        $serviceComponent = $this->codeComponent('service', 'SRV');
        $sectionComponent = $this->codeComponent('section_unit', 'SEC', $serviceComponent->id);
        $typeComponent = $this->codeComponent('document_type', 'MANL');

        $document = Document::query()->create([
            'document_type_id' => $documentType->id,
            'owner_user_id' => $owner->id,
            'owning_unit_id' => $unit->id,
            'created_by_user_id' => $owner->id,
            'title' => 'Documented procedure',
            'current_revision_label' => '1.0',
            'status' => 'under_revision',
        ]);
        $document->masterCodeRegisterEntry()->create([
            'service_component_id' => $serviceComponent->id,
            'section_component_id' => $sectionComponent->id,
            'document_type_component_id' => $typeComponent->id,
            'code_prefix' => 'ONP-SRV-SEC-MANL',
            'sequence_number' => 1,
            'base_code' => 'ONP-SRV-SEC-MANL-001',
            'requested_by_user_id' => $owner->id,
            'assigned_by_user_id' => $owner->id,
            'requested_at' => now(),
            'assigned_at' => now(),
        ]);
        $version = $document->versions()->create([
            'sequence_no' => 1,
            'revision_label' => '1.0',
            'status' => 'in_review',
            'created_by_user_id' => $owner->id,
        ]);

        $qmr = $this->role('QMR', 'Quality Management Representative', $reviewer, ['document.review.verify' => 'institution']);
        $mcc = $this->role('MCC', 'Medical Center Chief', $approver, ['document.master_copy.approve.manual_sop_guideline' => 'institution']);
        $kmt = $this->role('KMT', 'Knowledge Management Team', $kmtUser, ['document.release.registered' => 'institution']);
        $qmr->permissions()->attach(Permission::query()->firstOrCreate(
            ['code' => 'document.registry.view'],
            ['description' => 'View documents within the assigned authority scope.'],
        )->id, ['scope_type' => 'institution']);
        $mcc->permissions()->attach(Permission::query()->firstOrCreate(
            ['code' => 'document.registry.view'],
            ['description' => 'View documents within the assigned authority scope.'],
        )->id, ['scope_type' => 'institution']);
        $kmt->permissions()->attach(Permission::query()->firstOrCreate(
            ['code' => 'document.registry.view'],
            ['description' => 'View documents within the assigned authority scope.'],
        )->id, ['scope_type' => 'institution']);

        $template = WorkflowTemplate::query()->create([
            'template_key' => 'manual-approval',
            'name' => 'Manual approval',
            'is_active' => true,
            'created_by_user_id' => $owner->id,
        ]);
        $templateVersion = $template->versions()->create([
            'version_no' => 1,
            'document_type_id' => $documentType->id,
            'trigger_event' => 'version.submitted',
            'status' => 'published',
            'published_by_user_id' => $owner->id,
            'published_at' => now(),
        ]);
        $reviewStage = $templateVersion->stages()->create([
            'stage_key' => 'qmr-review', 'name' => 'QMR review', 'stage_order' => 1,
            'action_type' => 'review', 'is_mandatory' => true, 'required_decisions' => 1,
        ]);
        $reviewStage->eligibleRoles()->attach($qmr->id, ['scope_type' => 'institution']);
        $approvalStage = $templateVersion->stages()->create([
            'stage_key' => 'mcc-approval', 'name' => 'MCC approval', 'stage_order' => 2,
            'action_type' => 'approval', 'is_mandatory' => true, 'required_decisions' => 1,
        ]);
        $approvalStage->eligibleRoles()->attach($mcc->id, ['scope_type' => 'institution']);
        $releaseStage = $templateVersion->stages()->create([
            'stage_key' => 'kmt-release', 'name' => 'KMT release', 'stage_order' => 3,
            'action_type' => 'release', 'is_mandatory' => true, 'required_decisions' => 1,
        ]);

        $instance = $version->workflowInstance()->create([
            'workflow_template_version_id' => $templateVersion->id,
            'status' => 'in_progress',
            'started_by_user_id' => $owner->id,
            'started_at' => now(),
        ]);
        $reviewStageInstance = $instance->stages()->create([
            'workflow_template_stage_id' => $reviewStage->id, 'pass_no' => 1,
            'status' => 'active', 'started_at' => now(),
        ]);
        $approvalStageInstance = $instance->stages()->create([
            'workflow_template_stage_id' => $approvalStage->id, 'pass_no' => 1,
            'status' => 'pending',
        ]);
        $releaseStageInstance = $instance->stages()->create([
            'workflow_template_stage_id' => $releaseStage->id, 'pass_no' => 1,
            'status' => 'pending',
        ]);
        $reviewTask = $reviewStageInstance->tasks()->create([
            'role_id' => $qmr->id, 'status' => 'pending', 'assigned_at' => now(),
        ]);
        $approvalTask = $approvalStageInstance->tasks()->create([
            'role_id' => $mcc->id, 'status' => 'pending', 'assigned_at' => now(),
        ]);
        $releaseStageInstance->tasks()->create([
            'role_id' => $kmt->id, 'status' => 'pending', 'assigned_at' => now(),
        ]);

        return [$owner, $reviewer, $approver, $version, $instance, $reviewTask, $approvalTask];
    }

    private function user(string $email): User
    {
        return User::query()->create(['name' => $email, 'email' => $email, 'password' => 'password', 'is_active' => true]);
    }

    private function codeComponent(string $type, string $code, ?int $parentId = null): DocumentCodeComponent
    {
        return DocumentCodeComponent::query()->create([
            'source_key' => $type.'-'.$code,
            'parent_service_id' => $parentId,
            'component_type' => $type,
            'code' => $code,
            'name' => $code,
            'source_group' => 'Test fixture',
            'is_conflicted' => false,
            'is_active' => true,
        ]);
    }

    public function test_a_review_stage_assigns_and_accepts_the_owning_division_head(): void
    {
        $owner = $this->user('division-owner@example.test');
        $divisionHead = $this->user('division-head@example.test');
        $outsideHead = $this->user('outside-division-head@example.test');
        $unit = OrganizationalUnit::query()->create(['name' => 'Owning Division', 'unit_type' => 'division', 'is_active' => true]);
        $otherUnit = OrganizationalUnit::query()->create(['name' => 'Other Division', 'unit_type' => 'division', 'is_active' => true]);
        $documentType = DocumentType::query()->create(['code' => 'PROC', 'name' => 'Procedure', 'is_active' => true]);

        $document = Document::query()->create([
            'document_type_id' => $documentType->id,
            'owner_user_id' => $owner->id,
            'owning_unit_id' => $unit->id,
            'created_by_user_id' => $owner->id,
            'title' => 'Division Head review procedure',
            'current_revision_label' => '1.0',
            'status' => 'under_revision',
        ]);
        $version = $document->versions()->create([
            'sequence_no' => 1,
            'revision_label' => '1.0',
            'status' => 'in_review',
            'created_by_user_id' => $owner->id,
        ]);

        $divisionHeadRole = Role::query()->create(['code' => 'DIVISION_HEAD', 'name' => 'Division Head', 'is_active' => true]);
        foreach (['document.review.division_head', 'document.registry.view'] as $permissionCode) {
            $divisionHeadRole->permissions()->attach(Permission::query()->firstOrCreate(
                ['code' => $permissionCode],
                ['description' => $permissionCode.' test permission.'],
            )->id, ['scope_type' => 'organizational_unit']);
        }
        $divisionHead->roleAssignments()->create(['role_id' => $divisionHeadRole->id, 'organizational_unit_id' => $unit->id, 'work_scope' => 'Owning division', 'valid_from' => now()->toDateString()]);
        $outsideHead->roleAssignments()->create(['role_id' => $divisionHeadRole->id, 'organizational_unit_id' => $otherUnit->id, 'work_scope' => 'Other division', 'valid_from' => now()->toDateString()]);

        $template = WorkflowTemplate::query()->create(['template_key' => 'division-head-review', 'name' => 'Division Head review', 'is_active' => true, 'created_by_user_id' => $owner->id]);
        $templateVersion = $template->versions()->create([
            'version_no' => 1,
            'document_type_id' => $documentType->id,
            'trigger_event' => 'version.submitted',
            'status' => 'published',
            'published_by_user_id' => $owner->id,
            'published_at' => now(),
        ]);
        $reviewStage = $templateVersion->stages()->create([
            'stage_key' => 'division-head-review', 'name' => 'Division Head review', 'stage_order' => 1,
            'action_type' => 'review', 'is_mandatory' => true, 'required_decisions' => 1,
        ]);
        $reviewStage->eligibleRoles()->attach($divisionHeadRole->id, ['scope_type' => 'organizational_unit']);

        $instance = $version->workflowInstance()->create([
            'workflow_template_version_id' => $templateVersion->id,
            'status' => 'in_progress',
            'started_by_user_id' => $owner->id,
            'started_at' => now(),
        ]);
        $stageInstance = $instance->stages()->create([
            'workflow_template_stage_id' => $reviewStage->id, 'pass_no' => 1,
            'status' => 'active', 'started_at' => now(),
        ]);
        $task = $stageInstance->tasks()->create([
            'role_id' => $divisionHeadRole->id,
            'organizational_unit_id' => $unit->id,
            'status' => 'pending',
            'assigned_at' => now(),
        ]);

        $this->actingAs($outsideHead)
            ->postJson("/api/workflow-tasks/{$task->id}/decisions", ['decision_type' => 'reviewed'])
            ->assertForbidden();

        $this->actingAs($divisionHead)
            ->postJson("/api/workflow-tasks/{$task->id}/decisions", ['decision_type' => 'reviewed'])
            ->assertCreated()
            ->assertJsonPath('data.actor_user_id', $divisionHead->id);

        $this->assertDatabaseHas('workflow_decisions', [
            'workflow_task_id' => $task->id,
            'actor_user_id' => $divisionHead->id,
            'decision_type' => 'reviewed',
        ]);
    }

    /** @param array<string, string> $grants */
    private function role(string $code, string $name, User $user, array $grants): Role
    {
        $role = Role::query()->create(['code' => $code, 'name' => $name, 'is_active' => true]);
        foreach ($grants as $permissionCode => $scopeType) {
            $permission = Permission::query()->firstOrCreate(
                ['code' => $permissionCode],
                ['description' => $permissionCode.' test permission.'],
            );
            $role->permissions()->attach($permission->id, ['scope_type' => $scopeType]);
        }
        $user->roleAssignments()->create([
            'role_id' => $role->id,
            'work_scope' => 'Institution-wide test scope',
            'valid_from' => now()->toDateString(),
        ]);

        return $role;
    }
}
