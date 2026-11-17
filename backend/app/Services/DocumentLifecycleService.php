<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Institution;
use App\Models\User;
use App\Models\SubmissionValidationRun;
use App\Models\WorkflowDecision;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentLifecycleService
{
    public function __construct(
        private readonly AuthorizationService $authorization,
        private readonly DocumentAuditService $audit,
        private readonly WorkflowInstanceService $workflows,
        private readonly SubmissionValidationService $validation,
    ) {}

    /** @return list<string> */
    public function availableActions(DocumentVersion $version, User $actor): array
    {
        $version->loadMissing('document.documentType', 'document.masterCodeRegisterEntry.documentTypeComponent');
        $document = $version->document;
        $unitId = (int) $document->owning_unit_id;
        $code = $document->masterCodeRegisterEntry?->documentTypeComponent?->code;
        $profile = $document->documentType?->approval_flow_profile ?? 'source_defined';
        $actions = [];
        $hasWorkflow = $version->workflowInstance()->exists();

        if (in_array($version->status, ['draft', 'returned_for_revision'], true)
            && $profile !== 'deferred' && $code !== null && $code !== 'FORM'
            && ($profile !== 'phase4b_manual_sop_guideline' || in_array($code, ['MANL', 'PROC', 'GUID'], true))
            && $this->authorization->allows($actor, 'document.create_revise', $unitId)
            && $this->submissionChecksClear($version)) {
            $actions[] = 'submit';
        }
        if (! $hasWorkflow && $version->status === 'submitted' && $code !== null && $code !== 'FORM' && $this->authorization->allows($actor, 'document.review.verify')) {
            $actions[] = 'start_review';
        }
        if (! $hasWorkflow && $version->status === 'in_review') {
            if (in_array($code, ['POLC', 'GUID'], true)) {
                if (! $this->hasEvent($version, 'version.department_head_reviewed')
                    && $this->hasTemplateReviewer($actor, $unitId, 'DEPARTMENT_HEAD')) {
                    $actions[] = 'department_head_review';
                } elseif ($this->hasEvent($version, 'version.department_head_reviewed')
                    && ! $this->hasEvent($version, 'version.service_chief_reviewed')
                    && $this->hasTemplateReviewer($actor, $unitId, 'SERVICE_CHIEF')
                    && $this->eventActorId($version, 'version.department_head_reviewed') !== $actor->id) {
                    $actions[] = 'service_chief_review';
                }

                if ($this->hasEvent($version, 'version.department_head_reviewed')
                    && $this->hasEvent($version, 'version.service_chief_reviewed')
                    && $this->authorization->allows($actor, 'document.review.verify')) {
                    $actions[] = 'complete_review';
                }
            } elseif ($code !== 'FORM' && $this->authorization->allows($actor, 'document.review.verify')) {
                $actions[] = 'complete_review';
            }

            if ($this->canReturnForRevision($actor, $unitId, $code)) {
                $actions[] = 'return_for_revision';
            }
        }
        if (! $hasWorkflow && $version->status === 'ready_for_approval' && $this->authorization->allows($actor, 'document.review.verify')) {
            $actions[] = 'return_for_revision';
        }
        if (! $hasWorkflow && $version->status === 'ready_for_approval'
            && in_array($code, ['MANL', 'PROC', 'GUID'], true)
            && $actor->id !== $document->owner_user_id
            && $this->authorization->allows($actor, 'document.master_copy.approve.manual_sop_guideline')) {
            $actions[] = 'approve';
        }
        if ($version->status === 'approved') {
            if (! $version->proposed_effective_date
                && ! $version->effective_date
                && $this->authorization->allows($actor, 'document.create_revise', $unitId)) {
                $actions[] = 'propose_effective_date';
            }
            if ($version->proposed_effective_date
                && ! $version->effective_date
                && $this->authorization->allows($actor, 'document.release.registered')) {
                $actions[] = 'confirm_effective_date';
            }
        }
        if (in_array($version->status, ['approved', 'scheduled'], true)
            && $version->effective_date
            && $version->effective_date->format('Y-m-d') <= Institution::localDate()
            && $this->authorization->allows($actor, 'document.release.registered')
            && $this->mayKmtRelease($version, $actor)) {
            $actions[] = 'release';
        }

        return $actions;
    }

    private function mayKmtRelease(DocumentVersion $version, User $actor): bool
    {
        $workflowId = $version->workflowInstance()->value('id');
        if (! $workflowId) return true;
        $sameAccountVerified = WorkflowDecision::query()
            ->where('actor_user_id', $actor->id)
            ->where('decision_type', 'verified')
            ->whereHas('task.stageInstance', fn ($q) => $q->where('workflow_instance_id', $workflowId))
            ->exists();
        return ! $sameAccountVerified || app(Phase4BSettingService::class)->get('same_kmt_verify_release') === '1';
    }

    private function submissionChecksClear(DocumentVersion $version): bool
    {
        $files = $version->files()->where('is_current', true)->get();
        if ($files->isEmpty()) return false;
        foreach ($files as $file) {
            $run = SubmissionValidationRun::query()->where('document_file_id', $file->id)->latest('run_number')->first();
            if (! $run || $run->status === 'blocked') return false;
            if (collect($run->findings)->contains(function ($finding) {
                if (in_array($finding['result'] ?? '', ['pending', 'block'], true)) return true;
                return in_array($finding['rule'] ?? '', ['assigned_code_match', 'code_format'], true) && ($finding['result'] ?? '') !== 'pass';
            })) return false;
        }
        return true;
    }

    public function statusNote(DocumentVersion $version): ?string
    {
        $version->loadMissing('document.masterCodeRegisterEntry.documentTypeComponent');
        $code = $version->document->masterCodeRegisterEntry?->documentTypeComponent?->code;

        if ($code === 'FORM' && in_array($version->status, ['draft', 'submitted'], true)) {
            return 'The source requires Forms Committee approval before KMT coding for internal forms; internal/external form routing is not configured yet.';
        }
        if ($version->status === 'ready_for_approval' && ! in_array($code, ['MANL', 'PROC', 'GUID'], true)) {
            return 'The source does not name a final approver for this document type. Approval remains blocked while D-017 is open.';
        }
        if ($version->status === 'ready_for_approval') {
            return 'Awaiting the MCC final approval specified for manuals, procedures, and guidelines.';
        }
        if ($version->status === 'approved' && ! $version->proposed_effective_date && ! $version->effective_date) {
            return 'The Process Owner must propose an effective date for KMT to confirm before release.';
        }
        if ($version->status === 'approved' && $version->proposed_effective_date && ! $version->effective_date) {
            return 'Awaiting KMT confirmation of the Process Owner proposed effective date.';
        }
        if (in_array($version->status, ['approved', 'scheduled'], true) && ! $version->effective_date) {
            return 'KMT must confirm the proposed effective date before release.';
        }
        if ($version->status === 'scheduled' && $version->effective_date?->format('Y-m-d') > Institution::localDate()) {
            return 'KMT can release this version on or after its effective date.';
        }

        return null;
    }

    public function act(DocumentVersion $version, User $actor, string $action, ?string $reason = null, ?string $effectiveDate = null): DocumentVersion
    {
        return DB::transaction(function () use ($version, $actor, $action, $reason, $effectiveDate): DocumentVersion {
            $lockedVersion = DocumentVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();
            $document = Document::query()
                ->with('masterCodeRegisterEntry.documentTypeComponent')
                ->whereKey($lockedVersion->document_id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedVersion->setRelation('document', $document);

            if ($action === 'submit') {
                foreach ($lockedVersion->files()->where('is_current', true)->get() as $file) $this->validation->validate($file, $actor);
            }

            abort_unless(in_array($action, $this->availableActions($lockedVersion, $actor), true), 403, 'This action is not authorized for your active source-based role or the current document state.');

            if ($action === 'return_for_revision' && trim((string) $reason) === '') {
                throw ValidationException::withMessages(['reason' => 'A return reason is required.']);
            }

            if (in_array($action, ['propose_effective_date', 'confirm_effective_date'], true)) {
                if (! $effectiveDate) {
                    throw ValidationException::withMessages(['effective_date' => 'An effective date is required.']);
                }
                if ($action === 'propose_effective_date' && $lockedVersion->proposed_effective_date) {
                    throw ValidationException::withMessages(['effective_date' => 'A proposed date already exists for this version.']);
                }
                if ($action === 'propose_effective_date') {
                    $lockedVersion->update(['proposed_effective_date' => $effectiveDate]);
                    $this->audit->record('document_version', $lockedVersion->id, $document, $actor, 'version.effective_date_proposed', null, $effectiveDate);
                } else {
                    abort_unless($lockedVersion->proposed_effective_date?->format('Y-m-d') === $effectiveDate, 422, 'KMT must confirm the date proposed by the Process Owner.');
                    $nextStatus = $effectiveDate > Institution::localDate() ? 'scheduled' : 'approved';
                    $lockedVersion->update([
                        'effective_date' => $effectiveDate,
                        'status' => $nextStatus,
                    ]);
                    $this->audit->record('document_version', $lockedVersion->id, $document, $actor, 'version.effective_date_confirmed', null, $effectiveDate);
                    if ($nextStatus === 'scheduled') {
                        $this->audit->record('document_version', $lockedVersion->id, $document, $actor, 'version.scheduled', 'approved', 'scheduled');
                    }
                }

                return $lockedVersion->refresh();
            }

            if (in_array($action, ['department_head_review', 'service_chief_review'], true)) {
                $eventType = $action === 'department_head_review'
                    ? 'version.department_head_reviewed'
                    : 'version.service_chief_reviewed';
                $this->audit->record('document_version', $lockedVersion->id, $document, $actor, $eventType, 'in_review', 'in_review', $reason);

                return $lockedVersion->refresh();
            }

            [$nextState, $eventType] = match ($action) {
                'submit' => ['submitted', 'version.submitted'],
                'start_review' => ['in_review', 'version.review_started'],
                'complete_review' => ['ready_for_approval', 'version.reviewed'],
                'return_for_revision' => ['returned_for_revision', 'version.returned_for_revision'],
                'approve' => ['approved', 'version.approved'],
                'release' => ['released', 'version.released'],
            };

            $previousState = $lockedVersion->status;
            $lockedVersion->update(['status' => $nextState]);
            $this->audit->record('document_version', $lockedVersion->id, $document, $actor, $eventType, $previousState, $nextState, $reason);

            if ($action === 'submit') {
                $this->workflows->createForSubmission($lockedVersion, $actor);
            }

            if ($action === 'release') {
                $previouslyReleased = DocumentVersion::query()
                    ->where('document_id', $document->id)
                    ->where('id', '<>', $lockedVersion->id)
                    ->where('status', 'released')
                    ->lockForUpdate()
                    ->get();

                foreach ($previouslyReleased as $priorVersion) {
                    $priorVersion->update(['status' => 'superseded']);
                    $this->audit->record('document_version', $priorVersion->id, $document, $actor, 'version.superseded', 'released', 'superseded', 'Superseded when the next version was released.');
                }

                $previousDocumentState = $document->status;
                $document->update(['status' => 'active', 'effective_date' => $lockedVersion->effective_date]);
                $this->audit->record('document', $document->id, $document, $actor, 'document.activated', $previousDocumentState, 'active');
            }

            return $lockedVersion->refresh();
        });
    }

    private function hasEvent(DocumentVersion $version, string $eventType): bool
    {
        return AuditEvent::query()
            ->where('target_type', 'document_version')
            ->where('target_id', $version->id)
            ->where('event_type', $eventType)
            ->exists();
    }

    private function eventActorId(DocumentVersion $version, string $eventType): ?int
    {
        $actorId = AuditEvent::query()
            ->where('target_type', 'document_version')
            ->where('target_id', $version->id)
            ->where('event_type', $eventType)
            ->value('actor_user_id');

        return $actorId === null ? null : (int) $actorId;
    }

    private function hasTemplateReviewer(User $user, int $unitId, string $roleCode): bool
    {
        return $this->authorization->allows($user, 'document.review.policy_specific_guideline_template', $unitId)
            && $this->authorization->hasActiveRoleAssignment($user, $roleCode, $unitId);
    }

    private function canReturnForRevision(User $user, int $unitId, ?string $code): bool
    {
        if ($this->authorization->allows($user, 'document.review.verify')) {
            return true;
        }

        return in_array($code, ['POLC', 'GUID'], true)
            && ($this->hasTemplateReviewer($user, $unitId, 'DEPARTMENT_HEAD')
                || $this->hasTemplateReviewer($user, $unitId, 'SERVICE_CHIEF'));
    }
}
