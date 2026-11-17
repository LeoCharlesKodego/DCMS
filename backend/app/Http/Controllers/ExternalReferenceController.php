<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\ExternalReference;
use App\Services\AuthorizationService;
use App\Services\DocumentAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExternalReferenceController extends Controller
{
    public function index(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->authorizeManage($request, $authorization);
        return response()->json(['data' => ExternalReference::query()->with('documents:id,title')->orderBy('title')->get()]);
    }

    public function store(Request $request, AuthorizationService $authorization, DocumentAuditService $audit): JsonResponse
    {
        $this->authorizeManage($request, $authorization);
        $data = $this->validated($request);
        $reference = ExternalReference::query()->create($data + ['created_by_user_id' => $request->user()->id]);
        $audit->record('external_reference', (int) $reference->id, null, $request->user(), 'external_reference.created');
        return response()->json(['data' => $reference], 201);
    }

    public function update(Request $request, ExternalReference $externalReference, AuthorizationService $authorization, DocumentAuditService $audit): JsonResponse
    {
        $this->authorizeManage($request, $authorization);
        $data = $this->validated($request);
        $externalReference->update($data);
        $audit->record('external_reference', (int) $externalReference->id, null, $request->user(), 'external_reference.updated');
        return response()->json(['data' => $externalReference->fresh()]);
    }

    public function link(Request $request, Document $document, ExternalReference $externalReference, AuthorizationService $authorization): JsonResponse
    {
        abort_unless($authorization->allows($request->user(), 'document.registry.view', (int) $document->owning_unit_id), 404);
        $this->authorizeManage($request, $authorization);
        $document->externalReferences()->syncWithoutDetaching([$externalReference->id]);
        return response()->json(['data' => ['document_id' => $document->id, 'external_reference_id' => $externalReference->id]]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:500'], 'issuing_body' => ['nullable', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:255'], 'url' => ['nullable', 'url:http,https', 'max:2048'],
            'published_at' => ['nullable', 'date'], 'last_reviewed_at' => ['nullable', 'date'], 'review_due_at' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['active', 'under_review', 'superseded', 'withdrawn'])], 'notes' => ['nullable', 'string', 'max:4000'],
        ]);
    }

    private function authorizeManage(Request $request, AuthorizationService $authorization): void
    {
        abort_unless($authorization->allows($request->user(), 'document.register'), 403, 'Only an active KMT registration assignment may maintain external references.');
    }
}
