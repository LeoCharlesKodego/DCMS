<?php

namespace App\Services;

use App\Models\DocumentVersion;
use App\Models\Document;
use App\Models\Institution;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStageInstance;
use App\Models\WorkflowTemplateVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkflowInstanceService
{
    /** @var array<string, list<string>> */
    private const ACTION_PERMISSIONS = [
        'verification' => ['document.submission.verify'],
        'review' => ['document.review.verify', 'document.review.policy_specific_guideline_template'],
        'approval' => ['document.master_copy.approve.manual_sop_guideline'],
        'release' => ['document.release.registered'],
    ];

    public function __construct(
        private readonly DocumentAuditService $audit,
        private readonly WorkflowTemplateConfigurationService $templates,
    ) {}

    public function createForSubmission(DocumentVersion $version, User $submitter): WorkflowInstance
    {
        $version->loadMissing('document.documentType', 'document.masterCodeRegisterEntry.documentTypeComponent');
        $document = $version->document;
        $profile = $document->documentType?->approval_flow_profile ?? 'source_defined';
        $typeCode = $document->masterCodeRegisterEntry?->documentTypeComponent?->code;
        if ($profile === 'deferred') throw ValidationException::withMessages(['workflow' => 'This document type is deferred pending its routing decision.']);
        if ($profile === 'phase4b_manual_sop_guideline' && ! in_array($typeCode, ['MANL', 'PROC', 'GUID'], true)) {
            throw ValidationException::withMessages(['workflow' => 'The Phase 4B route is limited to manuals, procedures, and ordinary guidelines.']);
        }

        return DB::transaction(function () use ($version, $submitter, $document): WorkflowInstance {
            $existing = WorkflowInstance::query()->where('document_version_id', $version->id)->lockForUpdate()->first();
            if ($existing) {
                if ($existing->status !== 'returned') {
                    throw ValidationException::withMessages(['workflow' => 'A workflow instance already exists for this submitted version.']);
                }

                $templateVersion = WorkflowTemplateVersion::query()
                    ->with(['stages.eligibleRoles.permissions'])
                    ->whereKey($existing->workflow_template_version_id)
                    ->firstOrFail();
                $this->templates->assertPublishable($templateVersion);
                $passNo = (int) WorkflowStageInstance::query()
                    ->where('workflow_instance_id', $existing->id)
                    ->max('pass_no') + 1;
                $this->createStagePass($existing, $templateVersion, $document, $passNo);
                $existing->update(['status' => 'in_progress', 'completed_at' => null]);
                $this->audit->record('workflow_instance', $existing->id, $document, $submitter, 'workflow.resubmitted', 'returned', 'in_progress', null, [
                    'document_version_id' => (int) $version->id,
                    'workflow_template_version_id' => (int) $templateVersion->id,
                    'pass_no' => $passNo,
                ]);

                return $existing->load('stages.tasks');
            }

            $templateVersion = WorkflowTemplateVersion::query()
                ->with(['template', 'stages.eligibleRoles.permissions'])
                ->where('document_type_id', $document->document_type_id)
                ->where('trigger_event', 'version.submitted')
                ->where('status', 'published')
                ->whereHas('template', fn ($query) => $query->where('is_active', true))
                ->where(fn ($query) => $query->whereNull('category_id')->orWhere('category_id', $document->category_id))
                ->where(fn ($query) => $query->whereNull('organizational_unit_id')->orWhere('organizational_unit_id', $document->owning_unit_id))
                ->lockForUpdate()
                ->first();

            if (! $templateVersion) {
                throw ValidationException::withMessages(['workflow' => 'No active published workflow applies to this document type, category, and organizational unit. Unsupported document-type chains remain blocked.']);
            }
            $this->templates->assertPublishable($templateVersion);

            $instance = WorkflowInstance::query()->create([
                'document_version_id' => $version->id,
                'workflow_template_version_id' => $templateVersion->id,
                'status' => 'in_progress',
                'started_by_user_id' => $submitter->id,
                'started_at' => now(),
            ]);
            [$stageCount, $taskCount] = $this->createStagePass($instance, $templateVersion, $document, 1);

            $this->audit->record('workflow_instance', $instance->id, $document, $submitter, 'workflow.started', null, 'in_progress', null, [
                'document_version_id' => (int) $version->id,
                'workflow_template_version_id' => (int) $templateVersion->id,
                'stage_count' => $stageCount,
                'task_count' => $taskCount,
            ]);

            return $instance->load('stages.tasks');
        });
    }

    /** @return array{int, int} */
    private function createStagePass(WorkflowInstance $instance, WorkflowTemplateVersion $templateVersion, Document $document, int $passNo): array
    {
        $stages = $templateVersion->stages;
        $firstStage = $stages->first();
        $stageCount = 0;
        $taskCount = 0;

        foreach ($stages as $stage) {
            $stageInstance = WorkflowStageInstance::query()->create([
                'workflow_instance_id' => $instance->id,
                'workflow_template_stage_id' => $stage->id,
                'pass_no' => $passNo,
                'status' => $stage->is($firstStage) ? 'active' : 'pending',
                'started_at' => $stage->is($firstStage) ? now() : null,
            ]);
            $stageCount++;

            foreach ($stage->eligibleRoles as $role) {
                $scopeUnitId = $role->pivot->scope_type === 'organizational_unit'
                    ? (int) $document->owning_unit_id
                    : null;
                if (! $this->hasActiveAssignee($role, $scopeUnitId, $stage->action_type)) {
                    throw ValidationException::withMessages([
                        'workflow' => "No active user has the configured role and scope for stage '{$stage->name}'.",
                    ]);
                }

                $stageInstance->tasks()->create([
                    'role_id' => $role->id,
                    'organizational_unit_id' => $scopeUnitId,
                    'assigned_user_id' => null,
                    'status' => 'pending',
                    'assigned_at' => now(),
                ]);
                $taskCount++;
            }
        }

        return [$stageCount, $taskCount];
    }

    private function hasActiveAssignee(Role $role, ?int $organizationalUnitId, string $actionType): bool
    {
        $permissionCodes = self::ACTION_PERMISSIONS[$actionType] ?? [];
        if ($permissionCodes === []) {
            return false;
        }

        $today = Institution::localDate();

        return DB::table('user_role_assignments as assignments')
            ->join('users', 'users.id', '=', 'assignments.user_id')
            ->join('roles', 'roles.id', '=', 'assignments.role_id')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'roles.id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('assignments.role_id', $role->id)
            ->where('users.is_active', true)
            ->where('roles.is_active', true)
            ->whereIn('permissions.code', $permissionCodes)
            ->where('role_permissions.scope_type', $organizationalUnitId === null ? 'institution' : 'organizational_unit')
            ->whereDate('assignments.valid_from', '<=', $today)
            ->where(fn ($query) => $query->whereNull('assignments.valid_until')->orWhereDate('assignments.valid_until', '>=', $today))
            ->when($organizationalUnitId !== null,
                fn ($query) => $query->where('assignments.organizational_unit_id', $organizationalUnitId))
            ->exists();
    }
}
