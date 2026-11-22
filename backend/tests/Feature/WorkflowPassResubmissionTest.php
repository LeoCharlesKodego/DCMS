<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentCodeComponent;
use App\Models\DocumentType;
use App\Models\DocumentVersion;
use App\Models\OrganizationalUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTask;
use App\Models\WorkflowTemplate;
use App\Services\DocumentLifecycleService;
use App\Services\Phase4BSettingService;
use App\Services\WorkflowDecisionService;
use App\Services\WorkflowInstanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\AuthenticateSession;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * A returned submission closes its review pass. The resubmission opens the next pass,
 * so the verifier who acted before the return must get a fresh task, while separation
 * of duties still holds inside a single pass.
 */
class WorkflowPassResubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(AuthenticateSession::class);
    }

    public function test_the_same_verifier_gets_the_new_pass_after_a_return_and_resubmission(): void
    {
        $fixture = $this->fixture();
        $decisions = app(WorkflowDecisionService::class);

        $decisions->decide($this->onlyTask($decisions, $fixture['verifier'], $fixture['version']), $fixture['verifier'], 'verified');
        $decisions->decide($this->onlyTask($decisions, $fixture['reviewer'], $fixture['version']), $fixture['reviewer'], 'returned_for_revision', 'Please correct section 2.');

        $this->resubmit($fixture);

        // 1. The same verification role sees the new task in the new pass.
        $tasks = $decisions->visibleTasksFor($fixture['verifier'], $fixture['version']->fresh());
        $this->assertCount(1, $tasks);
        $this->assertSame('verification', $tasks[0]['action_type']);

        // 3. A user in another role still does not see the verification task.
        $this->assertSame([], $decisions->visibleTasksFor($fixture['approver'], $fixture['version']->fresh()));

        // 1 (continued). And can record the decision.
        $decisions->decide(WorkflowTask::query()->findOrFail($tasks[0]['id']), $fixture['verifier'], 'verified');
        $this->assertSame('in_review', $fixture['version']->fresh()->status);

        // 2. A user who already decided in the current pass does not see the next task.
        $this->assertSame([], $decisions->visibleTasksFor($fixture['verifier'], $fixture['version']->fresh()));
        $reviewTask = WorkflowTask::query()
            ->whereHas('stageInstance', fn ($query) => $query
                ->where('workflow_instance_id', $fixture['instance']->id)
                ->where('status', 'active'))
            ->firstOrFail();
        try {
            $decisions->decide($reviewTask, $fixture['verifier'], 'reviewed');
            $this->fail('An account that already decided in the current pass must not decide another stage.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_the_same_account_verify_and_release_setting_keeps_its_behaviour(): void
    {
        $fixture = $this->fixture();
        $decisions = app(WorkflowDecisionService::class);
        $lifecycle = app(DocumentLifecycleService::class);

        // Drive the version through a return, a resubmission, and the new pass to approval.
        $decisions->decide($this->onlyTask($decisions, $fixture['verifier'], $fixture['version']), $fixture['verifier'], 'verified');
        $decisions->decide($this->onlyTask($decisions, $fixture['reviewer'], $fixture['version']), $fixture['reviewer'], 'returned_for_revision', 'Please correct section 2.');
        $this->resubmit($fixture);
        $decisions->decide($this->onlyTask($decisions, $fixture['verifier'], $fixture['version']), $fixture['verifier'], 'verified');
        $decisions->decide($this->onlyTask($decisions, $fixture['reviewer'], $fixture['version']), $fixture['reviewer'], 'reviewed');
        $decisions->decide($this->onlyTask($decisions, $fixture['approver'], $fixture['version']), $fixture['approver'], 'approved');

        $version = $fixture['version']->fresh();
        $this->assertSame('approved', $version->status);
        $version->update(['effective_date' => now()->toDateString()]);

        // Setting off: the account that verified this instance cannot also release it,
        // while another holder of the same role can.
        $this->assertNotContains('release', $lifecycle->availableActions($version->fresh(), $fixture['verifier']));
        $this->assertContains('release', $lifecycle->availableActions($version->fresh(), $fixture['otherVerifier']));

        // Setting on: the verifying account may release, as before.
        app(Phase4BSettingService::class)->set('same_kmt_verify_release', '1', $fixture['owner']);
        $this->assertContains('release', $lifecycle->availableActions($version->fresh(), $fixture['verifier']));
    }

    private function onlyTask(WorkflowDecisionService $decisions, User $actor, DocumentVersion $version): WorkflowTask
    {
        $tasks = $decisions->visibleTasksFor($actor, $version->fresh());
        $this->assertCount(1, $tasks, 'Expected one visible task for '.$actor->email);

        return WorkflowTask::query()->findOrFail($tasks[0]['id']);
    }

    private function resubmit(array $fixture): void
    {
        // Reload first: the in-memory model still holds its creation status, so a plain
        // update would look clean and skip the write.
        $version = $fixture['version']->fresh();
        $version->update(['status' => 'submitted']);
        app(WorkflowInstanceService::class)->createForSubmission($version, $fixture['owner']);
    }

    /** @return array{owner: User, verifier: User, reviewer: User, approver: User, otherVerifier: User, version: DocumentVersion, instance: WorkflowInstance} */
    private function fixture(): array
    {
        $owner = $this->user('owner@example.test');
        $verifier = $this->user('verifier@example.test');
        $otherVerifier = $this->user('other-verifier@example.test');
        $reviewer = $this->user('division-head@example.test');
        $approver = $this->user('mcc@example.test');

        $unit = OrganizationalUnit::query()->create(['name' => 'Clinical Unit', 'unit_type' => 'division', 'is_active' => true]);
        $documentType = DocumentType::query()->create([
            'code' => 'PROC',
            'name' => 'Procedure',
            'is_active' => true,
            'approval_flow_profile' => 'phase4b_manual_sop_guideline',
        ]);

        $serviceComponent = $this->codeComponent('service', 'SRV');
        $sectionComponent = $this->codeComponent('section_unit', 'SEC', $serviceComponent->id);
        $typeComponent = $this->codeComponent('document_type', 'PROC');

        $document = Document::query()->create([
            'document_type_id' => $documentType->id,
            'owner_user_id' => $owner->id,
            'owning_unit_id' => $unit->id,
            'created_by_user_id' => $owner->id,
            'title' => 'Resubmission procedure',
            'current_revision_label' => '1.0',
            'status' => 'under_revision',
        ]);
        $document->masterCodeRegisterEntry()->create([
            'service_component_id' => $serviceComponent->id,
            'section_component_id' => $sectionComponent->id,
            'document_type_component_id' => $typeComponent->id,
            'code_prefix' => 'ONP-SRV-SEC-PROC',
            'sequence_number' => 1,
            'base_code' => 'ONP-SRV-SEC-PROC-001',
            'requested_by_user_id' => $owner->id,
            'assigned_by_user_id' => $owner->id,
            'requested_at' => now(),
            'assigned_at' => now(),
        ]);
        $version = $document->versions()->create([
            'sequence_no' => 1,
            'revision_label' => '1.0',
            'status' => 'submitted',
            'created_by_user_id' => $owner->id,
        ]);

        $verifyRole = $this->role('KMT', 'Knowledge Management Team', $verifier, [
            'document.submission.verify' => 'institution',
            'document.release.registered' => 'institution',
        ]);
        $this->grant($verifyRole, 'document.registry.view', 'institution');
        $otherVerifier->roleAssignments()->create([
            'role_id' => $verifyRole->id,
            'work_scope' => 'Institution-wide test scope',
            'valid_from' => now()->toDateString(),
        ]);
        $reviewRole = $this->role('DIVISION_HEAD', 'Division Head', $reviewer, [
            'document.review.division_head' => 'organizational_unit',
        ]);
        $this->grant($reviewRole, 'document.registry.view', 'organizational_unit');
        $reviewer->roleAssignments()->update(['organizational_unit_id' => $unit->id]);
        $approveRole = $this->role('MCC', 'Medical Center Chief', $approver, [
            'document.master_copy.approve.manual_sop_guideline' => 'institution',
        ]);
        $this->grant($approveRole, 'document.registry.view', 'institution');

        $template = WorkflowTemplate::query()->create([
            'template_key' => 'procedure-approval',
            'name' => 'Procedure approval',
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

        $stages = [
            ['verification', 'KMT verification', 1, $verifyRole, 'institution'],
            ['review', 'Division Head review', 2, $reviewRole, 'organizational_unit'],
            ['approval', 'MCC approval', 3, $approveRole, 'institution'],
            ['release', 'KMT release', 4, $verifyRole, 'institution'],
        ];
        $stageModels = [];
        foreach ($stages as [$actionType, $name, $order, $role, $scope]) {
            $stage = $templateVersion->stages()->create([
                'stage_key' => str($name)->slug()->value(),
                'name' => $name,
                'stage_order' => $order,
                'action_type' => $actionType,
                'is_mandatory' => true,
                'required_decisions' => 1,
            ]);
            $stage->eligibleRoles()->attach($role->id, ['scope_type' => $scope]);
            $stageModels[] = $stage;
        }

        $instance = $version->workflowInstance()->create([
            'workflow_template_version_id' => $templateVersion->id,
            'status' => 'in_progress',
            'started_by_user_id' => $owner->id,
            'started_at' => now(),
        ]);
        foreach ($stageModels as $index => $stage) {
            $stageInstance = $instance->stages()->create([
                'workflow_template_stage_id' => $stage->id,
                'pass_no' => 1,
                'status' => $index === 0 ? 'active' : 'pending',
                'started_at' => $index === 0 ? now() : null,
            ]);
            $stageInstance->tasks()->create([
                'role_id' => $stage->eligibleRoles()->first()->id,
                'organizational_unit_id' => $stage->eligibleRoles()->first()->pivot->scope_type === 'organizational_unit' ? $unit->id : null,
                'status' => 'pending',
                'assigned_at' => now(),
            ]);
        }

        return [
            'owner' => $owner,
            'verifier' => $verifier,
            'otherVerifier' => $otherVerifier,
            'reviewer' => $reviewer,
            'approver' => $approver,
            'version' => $version,
            'instance' => $instance,
        ];
    }

    private function user(string $email): User
    {
        return User::query()->create(['name' => $email, 'email' => $email, 'password' => 'password', 'is_active' => true]);
    }

    /** @param array<string, string> $grants */
    private function role(string $code, string $name, User $user, array $grants): Role
    {
        $role = Role::query()->create(['code' => $code, 'name' => $name, 'is_active' => true]);
        foreach ($grants as $permissionCode => $scopeType) {
            $this->grant($role, $permissionCode, $scopeType);
        }
        $user->roleAssignments()->create([
            'role_id' => $role->id,
            'work_scope' => 'Institution-wide test scope',
            'valid_from' => now()->toDateString(),
        ]);

        return $role;
    }

    private function grant(Role $role, string $permissionCode, string $scopeType): void
    {
        $permission = Permission::query()->firstOrCreate(
            ['code' => $permissionCode],
            ['description' => $permissionCode.' test permission.'],
        );
        $role->permissions()->attach($permission->id, ['scope_type' => $scopeType]);
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
}
