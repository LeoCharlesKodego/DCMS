<?php

namespace App\Http\Controllers;

use App\Models\WorkflowTask;
use App\Services\WorkflowDecisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkflowDecisionController extends Controller
{
    public function store(Request $request, WorkflowTask $workflowTask, WorkflowDecisionService $decisions): JsonResponse
    {
        $data = $request->validate([
            'decision_type' => ['required', 'string', Rule::in(['verified', 'reviewed', 'approved', 'returned_for_revision'])],
            'comment' => ['nullable', 'string', 'max:4000'],
        ]);

        $decision = $decisions->decide($workflowTask, $request->user(), $data['decision_type'], $data['comment'] ?? null);

        return response()->json(['data' => [
            'id' => $decision->id,
            'workflow_task_id' => $decision->workflow_task_id,
            'actor_user_id' => $decision->actor_user_id,
            'decision_type' => $decision->decision_type,
            'comment' => $decision->comment,
            'decided_at' => $decision->decided_at?->toISOString(),
        ]], 201);
    }
}
