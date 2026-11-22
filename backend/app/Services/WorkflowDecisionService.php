<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Institution;
use App\Models\User;
use App\Models\WorkflowDecision;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStageInstance;
use App\Models\WorkflowTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkflowDecisionService
{
    /** @var array<string, list<string>> */
    private const ACTION_PERMISSIONS = [
        'verification' => ['document.submission.verify'],
        'review' => ['document.review.verify', 'document.review.policy_specific_guideline_template', 'document.review.division_head'],
        'approval' => ['document.master_copy.approve.manual_sop_guideline'],
    ];

    public function __construct(
        private readonly DocumentAuditService $audit,
        private readonly AuthorizationService $authorization,
    ) {}

    /** @return list<array{id: int, stage_name: string, action_type: string, role: string, organizational_unit: string|null, decisions: list<string>}> */
    public function visibleTasksFor(User $actor, DocumentVersion $version): array
    {
        $version->loadMissing('document');
        $document = $version->document;
        if (! $this->authorization->allows($actor, 'document.registry.view', (int) $document->owning_unit_id)) {
            return [];
        }

        $instance = WorkflowInstance::query()
            ->where('document_version_id', $version->id)
            ->where('status', 'in_progress')
            ->first();
        if (! $instance || $actor->id === $instance->started_by_user_id || $actor->id === $version->created_by_user_id
            || $this->actorHasDecisionInCurrentPass($actor, $instance)) {
            return [];
        }

        $documentTypeCode = $document->masterCodeRegisterEntry()->with('documentTypeComponent')->first()?->documentTypeComponent?->code;

        return WorkflowTask::query()
            ->with(['role:id,name', 'organizationalUnit:id,name', 'stageInstance.templateStage'])
            ->where('status', 'pending')
            ->whereHas('stageInstance', fn ($query) => $query
                ->where('status', 'active')
                ->where('workflow_instance_id', $instance->id))
            ->get()
            ->filter(function (WorkflowTask $task) use ($actor, $documentTypeCode): bool {
                $action = $task->stageInstance->templateStage->action_type;
                if ($action === 'approval' && ! in_array($documentTypeCode, ['MANL', 'PROC', 'GUID'], true)) {
                    return false;
                }

                return in_array($action, ['verification', 'review', 'approval'], true)
                    && ($task->assigned_user_id === null || (int) $task->assigned_user_id === (int) $actor->id)
                    && $this->actorHasTaskAuthority($task, $actor, $action);
            })
            ->map(function (WorkflowTask $task): array {
                $action = $task->stageInstance->templateStage->action_type;

                return [
                    'id' => (int) $task->id,
                    'stage_name' => $task->stageInstance->templateStage->name,
                    'action_type' => $action,
                    'role' => $task->role->name,
                    'organizational_unit' => $task->organizationalUnit?->name,
                    'decisions' => match ($action) { 'approval' => ['approved', 'returned_for_revision'], 'verification' => ['verified', 'returned_for_revision'], default => ['reviewed', 'returned_for_revision'] },
                ];
            })
            ->values()
            ->all();
    }

    public function decide(WorkflowTask $task, User $actor, string $decisionType, ?string $comment = null): WorkflowDecision
    {
        return DB::transaction(function () use ($task, $actor, $decisionType, $comment): WorkflowDecision {
            $lockedTask = WorkflowTask::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
            $stageInstance = WorkflowStageInstance::query()
                ->with('templateStage')
                ->whereKey($lockedTask->workflow_stage_instance_id)
                ->lockForUpdate()
                ->firstOrFail();
            $instance = WorkflowInstance::query()
                ->whereKey($stageInstance->workflow_instance_id)
                ->lockForUpdate()
                ->firstOrFail();
            $version = DocumentVersion::query()->whereKey($instance->document_version_id)->lockForUpdate()->firstOrFail();
            $document = Document::query()
                ->with('masterCodeRegisterEntry.documentTypeComponent')
                ->whereKey($version->document_id)
                ->lockForUpdate()
                ->firstOrFail();
            $stage = $stageInstance->templateStage;

            abort_unless($instance->status === 'in_progress', 409, 'This workflow is no longer accepting decisions.');
            abort_unless($stageInstance->status === 'active', 409, 'Only the active workflow stage can receive a decision.');
            abort_unless($lockedTask->status === 'pending', 409, 'This task has already been decided or is unavailable.');
            abort_unless($this->authorization->allows($actor, 'document.registry.view', (int) $document->owning_unit_id), 403, 'Your role does not grant access to this document.');
            abort_unless($lockedTask->assigned_user_id === null || (int) $lockedTask->assigned_user_id === (int) $actor->id, 403, 'This task is assigned to another user.');
            abort_unless(in_array($stage->action_type, ['verification', 'review', 'approval'], true), 403, 'Recommendation and release decisions are unavailable under the current ONP authority rules.');
            abort_unless($this->actorHasTaskAuthority($lockedTask, $actor, $stage->action_type), 403, 'Your active role assignment does not authorize this task and scope.');
            abort_unless($actor->id !== $instance->started_by_user_id && $actor->id !== $version->created_by_user_id, 403, 'The submitter and version originator cannot review or approve their own submission.');
            abort_unless(! $this->actorHasDecisionInCurrentPass($actor, $instance), 403, 'A user who has already decided another stage in this workflow cannot decide this stage.');

            $allowedDecisions = match ($stage->action_type) {
                'verification' => ['verified', 'returned_for_revision'],
                'review' => ['reviewed', 'returned_for_revision'],
                default => ['approved', 'returned_for_revision'],
            };
            if ($stage->action_type === 'verification' && $decisionType === 'verified') {
                $this->assertSubmissionChecksResolved($version, $comment, $actor);
            }
            if (! in_array($decisionType, $allowedDecisions, true)) {
                throw ValidationException::withMessages(['decision_type' => 'This decision does not match the authority configured for the active stage.']);
            }
            if ($decisionType === 'returned_for_revision' && trim((string) $comment) === '') {
                throw ValidationException::withMessages(['comment' => 'A reason is required when returning a document for revision.']);
            }

            $documentTypeCode = $document->masterCodeRegisterEntry?->documentTypeComponent?->code;
            if ($stage->action_type === 'approval') {
                abort_unless(in_array($documentTypeCode, ['MANL', 'PROC', 'GUID'], true), 403, 'The ONP source only assigns MCC final approval to manuals, procedures, and guidelines.');
                abort_unless(! $this->actorHasDecisionInCurrentPass($actor, $instance), 403, 'The final approver must be different from all prior reviewers.');
            }

            $decision = WorkflowDecision::query()->create([
                'workflow_task_id' => $lockedTask->id,
                'actor_user_id' => $actor->id,
                'decision_type' => $decisionType,
                'comment' => $comment,
                'decided_at' => now(),
            ]);
            $lockedTask->update(['status' => 'completed']);
            $this->audit->record('workflow_decision', $decision->id, $document, $actor, 'workflow.decision_recorded', null, $decisionType, $comment, [
                'workflow_instance_id' => (int) $instance->id,
                'workflow_stage_instance_id' => (int) $stageInstance->id,
                'workflow_task_id' => (int) $lockedTask->id,
                'action_type' => $stage->action_type,
            ]);

            if ($decisionType === 'returned_for_revision') {
                $this->returnForRevision($instance, $stageInstance, $version, $document, $actor, $lockedTask, $comment);

                return $decision;
            }

            $this->completeStageOrAdvance($instance, $stageInstance, $version, $document, $actor, $lockedTask);

            return $decision;
        });
    }

    private function actorHasTaskAuthority(WorkflowTask $task, User $actor, string $actionType): bool
    {
        $permissionCodes = self::ACTION_PERMISSIONS[$actionType] ?? [];
        if ($permissionCodes === [] || ! $actor->is_active) {
            return false;
        }

        $today = Institution::localDate();
        $query = DB::table('user_role_assignments as assignments')
            ->join('roles', 'roles.id', '=', 'assignments.role_id')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'roles.id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('assignments.user_id', $actor->id)
            ->where('assignments.role_id', $task->role_id)
            ->where('roles.is_active', true)
            ->whereIn('permissions.code', $permissionCodes)
            ->where('role_permissions.scope_type', $task->organizational_unit_id === null ? 'institution' : 'organizational_unit')
            ->whereDate('assignments.valid_from', '<=', $today)
            ->where(fn ($query) => $query->whereNull('assignments.valid_until')->orWhereDate('assignments.valid_until', '>=', $today));

        if ($task->organizational_unit_id !== null) {
            $query->where('assignments.organizational_unit_id', $task->organizational_unit_id);
        }

        return $query->exists();
    }

    private function assertSubmissionChecksResolved(DocumentVersion $version, ?string $reason, User $actor): void
    {
        $version->loadMissing('files');
        $hasResolvable = false;
        $runs = [];
        foreach ($version->files()->where('is_current', true)->get() as $file) {
            $run = \App\Models\SubmissionValidationRun::query()->where('document_file_id', $file->id)->latest('run_number')->first();
            abort_unless($run && $run->status !== 'blocked', 422, 'Submission checks are missing or contain a hard block.');
            $runs[] = $run;
            foreach ($run->findings as $finding) {
                abort_unless(($finding['result'] ?? null) !== 'block', 422, 'A hard block cannot be waived; correct the file or identity and resubmit.');
                if (in_array($finding['result'] ?? null, ['warning', 'manual', 'not_checked'], true)) $hasResolvable = true;
            }
        }
        abort_unless(! $hasResolvable || trim((string) $reason) !== '', 422, 'KMT must record a reason for resolving warnings or manual checks.');
        if ($hasResolvable) foreach ($runs as $run) {
            $findings = $run->findings;
            foreach ($findings as &$finding) if (in_array($finding['result'] ?? null, ['warning', 'manual', 'not_checked'], true)) {
                $finding['resolution_reason'] = trim((string) $reason);
                $finding['resolved_by_user_id'] = (int) $actor->id;
                $finding['resolved_at'] = now()->toISOString();
            }
            $run->update(['findings' => $findings]);
        }
    }

    /**
     * Separation of duties applies within one review pass, not across the whole life of
     * the instance. A returned submission closes its pass; the resubmission starts a new
     * one, so a reviewer who acted before the return must be able to review again, while
     * one account still cannot decide two stages of the same pass. Decisions from earlier,
     * closed passes stay on record and remain visible in the audit history.
     */
    private function actorHasDecisionInCurrentPass(User $actor, WorkflowInstance $instance): bool
    {
        $currentPass = WorkflowStageInstance::query()
            ->where('workflow_instance_id', $instance->id)
            ->whereIn('status', ['pending', 'active'])
            ->max('pass_no');

        if ($currentPass === null) {
            $currentPass = WorkflowStageInstance::query()
                ->where('workflow_instance_id', $instance->id)
                ->max('pass_no');
        }

        return WorkflowDecision::query()
            ->where('actor_user_id', $actor->id)
            ->whereHas('task.stageInstance', fn ($query) => $query
                ->where('workflow_instance_id', $instance->id)
                ->where('pass_no', (int) $currentPass))
            ->exists();
    }

    private function returnForRevision(
        WorkflowInstance $instance,
        WorkflowStageInstance $stageInstance,
        DocumentVersion $version,
        Document $document,
        User $actor,
        WorkflowTask $actedTask,
        string $reason,
    ): void {
        $stageInstance->update(['status' => 'returned', 'completed_at' => now()]);
        WorkflowStageInstance::query()
            ->where('workflow_instance_id', $instance->id)
            ->whereKeyNot($stageInstance->id)
            ->whereIn('status', ['pending', 'active'])
            ->update(['status' => 'cancelled', 'completed_at' => now()]);
        WorkflowTask::query()
            ->whereHas('stageInstance', fn ($query) => $query->where('workflow_instance_id', $instance->id))
            ->where('id', '<>', $actedTask->id)
            ->whereIn('status', ['pending', 'claimed'])
            ->update(['status' => 'cancelled']);
        $instance->update(['status' => 'returned', 'completed_at' => now()]);
        $previousStatus = $version->status;
        $version->update(['status' => 'returned_for_revision']);
        $this->audit->record('document_version', $version->id, $document, $actor, 'version.returned_for_revision', $previousStatus, 'returned_for_revision', $reason, [
            'workflow_instance_id' => (int) $instance->id,
            'workflow_stage_instance_id' => (int) $stageInstance->id,
        ]);
    }

    private function completeStageOrAdvance(
        WorkflowInstance $instance,
        WorkflowStageInstance $stageInstance,
        DocumentVersion $version,
        Document $document,
        User $actor,
        WorkflowTask $actedTask,
    ): void {
        WorkflowTask::query()
            ->where('workflow_stage_instance_id', $stageInstance->id)
            ->where('id', '<>', $actedTask->id)
            ->whereIn('status', ['pending', 'claimed'])
            ->update(['status' => 'cancelled']);

        $stageInstance->update(['status' => 'completed', 'completed_at' => now()]);
        $nextStage = WorkflowStageInstance::query()
            ->with('templateStage')
            ->where('workflow_instance_id', $instance->id)
            ->where('status', 'pending')
            ->get()
            ->sortBy(fn (WorkflowStageInstance $candidate) => $candidate->templateStage->stage_order)
            ->first();

        if ($nextStage) {
            if ($stageInstance->templateStage->action_type === 'approval') {
                abort_unless($nextStage->templateStage->action_type === 'release', 409, 'No configured workflow stages may follow final approval except the separate release step.');
                WorkflowStageInstance::query()
                    ->where('workflow_instance_id', $instance->id)
                    ->whereIn('status', ['pending', 'active'])
                    ->update(['status' => 'skipped', 'completed_at' => now()]);
                WorkflowTask::query()
                    ->whereHas('stageInstance', fn ($query) => $query->where('workflow_instance_id', $instance->id))
                    ->whereIn('status', ['pending', 'claimed'])
                    ->update(['status' => 'cancelled']);
                $instance->update(['status' => 'completed', 'completed_at' => now()]);
                $previousStatus = $version->status;
                $version->update(['status' => 'approved']);
                $this->audit->record('document_version', $version->id, $document, $actor, 'version.approved', $previousStatus, 'approved');
                $this->audit->record('workflow_instance', $instance->id, $document, $actor, 'workflow.approved', 'in_progress', 'completed');

                return;
            }

            $nextStage->update(['status' => 'active', 'started_at' => now()]);
            if ($nextStage->templateStage->action_type === 'approval') {
                $previousStatus = $version->status;
                $version->update(['status' => 'ready_for_approval']);
                $this->audit->record('document_version', $version->id, $document, $actor, 'version.reviewed', $previousStatus, 'ready_for_approval');
            } elseif ($version->status === 'submitted') {
                $version->update(['status' => 'in_review']);
                $this->audit->record('document_version', $version->id, $document, $actor, 'version.review_started', 'submitted', 'in_review');
            }

            return;
        }

        if ($stageInstance->templateStage->action_type === 'approval') {
            $instance->update(['status' => 'completed', 'completed_at' => now()]);
            $previousStatus = $version->status;
            $version->update(['status' => 'approved']);
            $this->audit->record('document_version', $version->id, $document, $actor, 'version.approved', $previousStatus, 'approved');
            $this->audit->record('workflow_instance', $instance->id, $document, $actor, 'workflow.approved', 'in_progress', 'completed');
        }
    }
}
