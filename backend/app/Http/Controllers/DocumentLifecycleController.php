<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Services\DocumentLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DocumentLifecycleController extends Controller
{
    public function act(Request $request, Document $document, DocumentVersion $documentVersion, DocumentLifecycleService $lifecycle, DocumentVersionController $versions): JsonResponse
    {
        abort_unless((int) $documentVersion->document_id === (int) $document->id, 404, 'The document version was not found.');

        $data = $request->validate([
            'action' => ['required', Rule::in([
                'submit', 'start_review', 'department_head_review', 'service_chief_review',
                'complete_review', 'return_for_revision', 'approve', 'propose_effective_date', 'confirm_effective_date', 'release',
            ])],
            'reason' => ['nullable', 'string', 'max:4000'],
            'effective_date' => ['required_if:action,propose_effective_date,confirm_effective_date', 'nullable', 'date_format:Y-m-d'],
        ]);

        $updated = $lifecycle->act($documentVersion, $request->user(), $data['action'], $data['reason'] ?? null, $data['effective_date'] ?? null);

        return response()->json(['data' => $versions->versionPayload($updated->load('files'))]);
    }
}
