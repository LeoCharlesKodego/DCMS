<?php

namespace App\Services;

use App\Models\Role;
use App\Models\WorkflowTemplateStage;
use App\Models\WorkflowTemplateVersion;
use Illuminate\Validation\ValidationException;

class WorkflowTemplateConfigurationService
{
    /** @var array<string, list<string>> */
    private const ACTION_PERMISSIONS = [
        'verification' => ['document.submission.verify'],
        'review' => ['document.review.verify', 'document.review.policy_specific_guideline_template', 'document.review.division_head'],
        'recommendation' => [],
        'approval' => ['document.master_copy.approve.manual_sop_guideline'],
        'release' => ['document.release.registered'],
    ];

    /** @return array<string, list<array{id: int, code: string, name: string, scope_types: list<string>}>> */
    public function eligibleRoleOptions(): array
    {
        $roles = Role::query()->where('is_active', true)->with('permissions')->orderBy('name')->get();
        $options = [];

        foreach (array_keys(self::ACTION_PERMISSIONS) as $actionType) {
            $permissionCodes = self::ACTION_PERMISSIONS[$actionType];
            $options[$actionType] = $roles->map(function (Role $role) use ($permissionCodes): ?array {
                $scopes = $role->permissions
                    ->whereIn('code', $permissionCodes)
                    ->map(fn ($permission) => $permission->pivot->scope_type)
                    ->unique()
                    ->values()
                    ->all();

                return $scopes === [] ? null : [
                    'id' => (int) $role->id,
                    'code' => $role->code,
                    'name' => $role->name,
                    'scope_types' => $scopes,
                ];
            })->filter()->values()->all();
        }

        return $options;
    }

    /** @param array<string, mixed> $stage */
    public function validateStageRoles(array $stage, int $index): void
    {
        $actionType = $stage['action_type'];
        $permissionCodes = self::ACTION_PERMISSIONS[$actionType] ?? [];
        if ($actionType === 'recommendation' || $permissionCodes === []) {
            throw ValidationException::withMessages([
                "stages.$index.action_type" => 'The ONP source does not assign a recommendation stage authority for this workflow.',
            ]);
        }

        foreach ($stage['roles'] as $roleIndex => $roleData) {
            $role = Role::query()->where('is_active', true)->with('permissions')->find($roleData['role_id']);
            $hasScope = $role?->permissions->contains(fn ($permission) =>
                in_array($permission->code, $permissionCodes, true)
                && $permission->pivot->scope_type === $roleData['scope_type']
            );

            if (! $hasScope) {
                throw ValidationException::withMessages([
                    "stages.$index.roles.$roleIndex.role_id" => 'Choose an active ONP role that already has the source-defined permission for this action and scope.',
                ]);
            }
        }
    }

    public function assertPublishable(WorkflowTemplateVersion $version): void
    {
        $stages = $version->stages()->with('eligibleRoles.permissions')->get();
        if ($stages->isEmpty()) {
            throw ValidationException::withMessages(['stages' => 'A workflow version needs at least one stage.']);
        }

        if ($stages->contains(fn (WorkflowTemplateStage $stage) => (int) $stage->required_decisions !== 1)) {
            throw ValidationException::withMessages(['stages' => 'Quorum greater than one is unavailable while D-017 is open.']);
        }
        if ($stages->contains(fn (WorkflowTemplateStage $stage) => ! $stage->is_mandatory)) {
            throw ValidationException::withMessages(['stages' => 'Optional stages are unavailable while the ONP source does not define which review and approval steps may be skipped.']);
        }
        if ($stages->contains(fn (WorkflowTemplateStage $stage) => $stage->parallel_group !== null)) {
            throw ValidationException::withMessages(['stages' => 'The ONP source does not define parallel workflow stages; they remain unavailable while D-017 is open.']);
        }
        if ($stages->groupBy('stage_order')->contains(fn ($sameOrder) => $sameOrder->count() > 1)) {
            throw ValidationException::withMessages(['stages' => 'Sequential stages must have distinct order values.']);
        }
        if (! $version->documentType()->where('is_active', true)->exists()
            || ($version->category_id && ! $version->category()->where('is_active', true)->exists())
            || ($version->organizational_unit_id && ! $version->organizationalUnit()->where('is_active', true)->exists())) {
            throw ValidationException::withMessages(['document_type_id' => 'The selected document type, category, and organizational scope must remain active to publish.']);
        }

        foreach ($stages as $index => $stage) {
            $this->validateStageRoles([
                'action_type' => $stage->action_type,
                'roles' => $stage->eligibleRoles->map(fn (Role $role) => [
                    'role_id' => (int) $role->id,
                    'scope_type' => $role->pivot->scope_type,
                ])->all(),
            ], $index);
        }

        $qmrReviewOrder = $stages
            ->filter(fn (WorkflowTemplateStage $stage) => $stage->action_type === 'review'
                && $stage->eligibleRoles->contains(fn (Role $role) => $role->permissions->contains('code', 'document.review.verify')))
            ->min('stage_order');
        $approvalOrder = $stages->where('action_type', 'approval')->min('stage_order');
        $releaseOrder = $stages->where('action_type', 'release')->min('stage_order');

        $profile = $version->documentType()->value('approval_flow_profile');
        $verificationOrder = $stages->where('action_type', 'verification')->min('stage_order');
        $divisionReviewOrder = $stages->filter(fn (WorkflowTemplateStage $stage) => $stage->action_type === 'review'
            && $stage->eligibleRoles->contains(fn (Role $role) => $role->permissions->contains('code', 'document.review.division_head')))->min('stage_order');
        if ($profile === 'phase4b_manual_sop_guideline') {
            if ($verificationOrder === null || $divisionReviewOrder === null || $approvalOrder === null
                || ! ($verificationOrder < $divisionReviewOrder && $divisionReviewOrder < $approvalOrder)
                || $qmrReviewOrder !== null) {
                throw ValidationException::withMessages(['stages' => 'The Phase 4B profile requires KMT verification, owning Division Head review, then MCC approval; QMR is excluded.']);
            }
        } elseif ($profile === 'deferred') {
            throw ValidationException::withMessages(['stages' => 'This document type is outside the configured submission routes pending D-017.']);
        } elseif ($approvalOrder === null || $qmrReviewOrder === null || $qmrReviewOrder >= $approvalOrder) {
            throw ValidationException::withMessages(['stages' => 'A published workflow must include QMR review before an authorized final-approval stage.']);
        }
        if ($releaseOrder === null || $releaseOrder <= $approvalOrder) {
            throw ValidationException::withMessages(['stages' => 'A published workflow must place KMT release after final approval.']);
        }

        $policyReviewRoleIds = Role::query()
            ->where('is_active', true)
            ->whereHas('permissions', fn ($query) => $query->where('permissions.code', 'document.review.policy_specific_guideline_template'))
            ->pluck('roles.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $configuredPolicyReviewStages = $stages->filter(fn (WorkflowTemplateStage $stage) =>
            $stage->action_type === 'review'
            && $stage->eligibleRoles->contains(fn (Role $role) => in_array((int) $role->id, $policyReviewRoleIds, true))
        );
        if ($configuredPolicyReviewStages->isNotEmpty()) {
            if ($configuredPolicyReviewStages->count() < count($policyReviewRoleIds)
                || $configuredPolicyReviewStages->max('stage_order') >= $approvalOrder) {
                throw ValidationException::withMessages(['stages' => 'Both source-assigned policy/specific-guideline reviews must occur before final approval.']);
            }
            $configuredRoleIds = $configuredPolicyReviewStages->flatMap(fn (WorkflowTemplateStage $stage) => $stage->eligibleRoles
                ->filter(fn (Role $role) => in_array((int) $role->id, $policyReviewRoleIds, true))
                ->pluck('id'))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            if (array_diff($policyReviewRoleIds, $configuredRoleIds) !== []) {
                throw ValidationException::withMessages(['stages' => 'When using the policy/specific-guideline reviewer permission, include both source-assigned template reviewer roles.']);
            }
        }
    }
}
