<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\OrganizationalUnit;
use App\Services\AuthorizationService;
use App\Services\DocumentDistributionService;
use App\Services\WorkflowDecisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function show(Request $request, AuthorizationService $auth, WorkflowDecisionService $tasks, DocumentDistributionService $distributions): JsonResponse
    {
        $user = $request->user();
        $widgets = [];
        if ($user->is_system_admin) $widgets[] = ['key' => 'system_settings', 'label' => 'Account and settings administration'];
        $unitIds = $auth->organizationalUnitIdsFor($user, 'document.registry.view');
        $institutionView = $auth->allows($user, 'document.registry.view');
        $query = Document::query()->with(['versions.files', 'versions.workflowInstance.stages.tasks', 'masterCodeRegisterEntry']);
        if (! $institutionView) {
            if ($unitIds === []) {
                if ($user->distributionRecipients()->exists()) {
                    $inbox = $distributions->inboxFor($user);
                    $widgets[] = ['key' => 'staff_inbox', 'label' => 'My distributed documents awaiting acknowledgment', 'count' => collect($inbox)->where('acknowledgment_status', 'pending')->count()];
                }
                return response()->json(['widgets' => $widgets, 'scope' => []]);
            }
            $query->whereIn('owning_unit_id', $unitIds);
        }
        $documents = $query->get();
        $versions = $documents->flatMap(fn ($document) => $document->versions);
        $roleCodes = $user->roleAssignments()->with('role')->get()->pluck('role.code')->unique()->all();
        if ($auth->allows($user, 'document.create_revise')) {
            $widgets[] = ['key' => 'my_documents', 'label' => 'Own-unit drafts and returned documents', 'count' => $versions->whereIn('status', ['draft', 'returned_for_revision'])->count()];
            $widgets[] = ['key' => 'effective_date_proposals', 'label' => 'Effective dates to propose after MCC approval', 'count' => $versions->filter(fn ($version) => $version->status === 'approved' && ! $version->proposed_effective_date && ! $version->effective_date)->count()];
        }
        if ($auth->allows($user, 'document.submission.verify')) {
            $widgets[] = ['key' => 'kmt_verification', 'label' => 'Items awaiting KMT verification or code assignment', 'count' => $versions->whereIn('status', ['draft', 'submitted'])->count()];
            $widgets[] = ['key' => 'items_to_release', 'label' => 'Approved documents awaiting effective date or release', 'count' => $versions->whereIn('status', ['approved', 'scheduled'])->count()];
        }
        if ($auth->allows($user, 'document.review.division_head', (int) ($unitIds[0] ?? 0)) || in_array('DIVISION_HEAD', $roleCodes, true)) {
            $widgets[] = ['key' => 'division_reviews', 'label' => 'Owning-unit Division Head reviews', 'count' => $this->visibleTaskCount($versions, $tasks, $user, 'review')];
        }
        if ($auth->allows($user, 'document.master_copy.approve.manual_sop_guideline')) {
            $widgets[] = ['key' => 'mcc_approvals', 'label' => 'MCC approval items', 'count' => $this->visibleTaskCount($versions, $tasks, $user, 'approval')];
        }
        if ($auth->allows($user, 'records.manage')) $widgets[] = ['key' => 'records', 'label' => 'Records and disposition work', 'count' => null];
        if ($auth->allows($user, 'document.distribute')) $widgets[] = ['key' => 'acknowledgments', 'label' => 'Distribution acknowledgments and exemptions', 'count' => null];
        if ($user->distributionRecipients()->exists()) {
            $inbox = $distributions->inboxFor($user);
            $widgets[] = ['key' => 'staff_inbox', 'label' => 'My distributed documents awaiting acknowledgment', 'count' => collect($inbox)->where('acknowledgment_status', 'pending')->count()];
        }

        $scope = $institutionView ? ['Institution-wide'] : OrganizationalUnit::query()->whereIn('id', $unitIds)->orderBy('name')->pluck('name')->all();
        return response()->json(['widgets' => $widgets, 'scope' => $scope]);
    }

    private function visibleTaskCount($versions, WorkflowDecisionService $tasks, $user, string $action): int
    {
        return $versions->sum(fn ($version) => count(array_filter($tasks->visibleTasksFor($user, $version), fn ($task) => $task['action_type'] === $action)));
    }
}
