<?php

namespace App\Http\Controllers;

use App\Models\DocumentMigrationBatch;
use App\Models\DocumentMigrationItem;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\AuthorizationService;
use App\Services\DocumentMigrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentMigrationController extends Controller
{
    public function options(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->authorizeMigration($request, $authorization);
        return response()->json([
            'document_types' => DocumentType::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'units' => OrganizationalUnit::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'owners' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function index(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->authorizeMigration($request, $authorization);
        $batches = DocumentMigrationBatch::query()->withCount('items')->with('createdBy:id,name')->orderByDesc('created_at')->get();
        return response()->json(['data' => $batches]);
    }

    public function store(Request $request, AuthorizationService $authorization, DocumentMigrationService $migration): JsonResponse
    {
        $this->authorizeMigration($request, $authorization);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'source_description' => ['nullable', 'string', 'max:1000']]);
        $batch = $migration->createBatch($request->user(), $data['name'], $data['source_description'] ?? null);
        return response()->json(['data' => $batch], 201);
    }

    public function show(Request $request, DocumentMigrationBatch $documentMigrationBatch, AuthorizationService $authorization): JsonResponse
    {
        $this->authorizeMigration($request, $authorization);
        return response()->json([
            'data' => $documentMigrationBatch->load(['items.uploadedBy:id,name', 'items.verifiedBy:id,name']),
            'reconciliation' => app(DocumentMigrationService::class)->reconciliation($documentMigrationBatch),
        ]);
    }

    public function upload(Request $request, DocumentMigrationBatch $documentMigrationBatch, AuthorizationService $authorization, DocumentMigrationService $migration): JsonResponse
    {
        $this->authorizeMigration($request, $authorization);
        $data = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:100'],
            'files.*' => ['required', 'file', 'mimes:pdf,docx,xlsx,jpg,jpeg,png', 'extensions:pdf,docx,xlsx,jpg,jpeg,png', 'max:'.config('app.upload_max_kb', 51200)],
        ]);
        $items = $migration->addFiles($documentMigrationBatch, $request->user(), $request->file('files', []));
        return response()->json(['data' => $items], 201);
    }

    public function mapping(Request $request, DocumentMigrationItem $documentMigrationItem, AuthorizationService $authorization, DocumentMigrationService $migration): JsonResponse
    {
        $this->authorizeMigration($request, $authorization);
        $data = $request->validate([
            'source_metadata' => ['required', 'array'],
            'source_metadata.original_code' => ['nullable', 'string', 'max:512'],
            'source_metadata.original_title' => ['nullable', 'string', 'max:500'],
            'source_metadata.document_type' => ['nullable', 'string', 'max:255'],
            'source_metadata.category' => ['nullable', 'string', 'max:255'],
            'source_metadata.revision_label' => ['nullable', 'string', 'max:100'],
            'source_metadata.approval_authority' => ['nullable', 'string', 'max:255'],
            'source_metadata.approval_date' => ['nullable', 'string', 'max:100'],
            'source_metadata.effective_date' => ['nullable', 'string', 'max:100'],
            'source_metadata.owner' => ['nullable', 'string', 'max:255'],
            'source_metadata.department' => ['nullable', 'string', 'max:255'],
            'source_metadata.historical_version_of' => ['nullable', 'string', 'max:512'],
            'mapped_metadata' => ['nullable', 'array'],
            'source_location' => ['nullable', 'string', 'max:1000'],
        ]);
        $item = $migration->updateMapping($documentMigrationItem, $request->user(), $data['source_metadata'], $data['mapped_metadata'] ?? [], $data['source_location'] ?? null);
        return response()->json(['data' => $item]);
    }

    public function verify(Request $request, DocumentMigrationItem $documentMigrationItem, AuthorizationService $authorization, DocumentMigrationService $migration): JsonResponse
    {
        $this->authorizeMigration($request, $authorization);
        $data = $request->validate([
            'verification_status' => ['required', 'in:verified,requires_correction,conflicting_information,obsolete,superseded,rejected_for_migration'],
            'verification_notes' => ['nullable', 'string', 'max:4000'],
        ]);
        return response()->json(['data' => $migration->verify($documentMigrationItem, $request->user(), $data['verification_status'], $data['verification_notes'] ?? null)]);
    }

    public function register(Request $request, DocumentMigrationItem $documentMigrationItem, AuthorizationService $authorization, DocumentMigrationService $migration): JsonResponse
    {
        $this->authorizeMigration($request, $authorization);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'document_type_id' => ['required', 'integer', 'exists:document_types,id'],
            'owning_unit_id' => ['required', 'integer', 'exists:organizational_units,id'],
            'owner_user_id' => ['required', 'integer', 'exists:users,id'],
            'revision_label' => ['required', 'string', 'max:16'],
        ]);
        $document = $migration->registerVerified($documentMigrationItem, $request->user(), $data);
        return response()->json(['data' => ['id' => $document->id, 'title' => $document->title, 'status' => $document->status, 'revision_label' => $document->current_revision_label]], 201);
    }

    public function sourceFile(Request $request, DocumentMigrationItem $documentMigrationItem, AuthorizationService $authorization): mixed
    {
        $this->authorizeMigration($request, $authorization);
        abort_unless(Storage::disk('local')->exists($documentMigrationItem->storage_key), 404);
        $actual = hash_file('sha256', Storage::disk('local')->path($documentMigrationItem->storage_key));
        abort_unless($actual !== false && hash_equals($documentMigrationItem->sha256, $actual), 500, 'The imported source failed its integrity check.');
        return Storage::disk('local')->download($documentMigrationItem->storage_key, basename($documentMigrationItem->original_filename), ['Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function report(Request $request, DocumentMigrationBatch $documentMigrationBatch, AuthorizationService $authorization, DocumentMigrationService $migration): JsonResponse
    {
        $this->authorizeMigration($request, $authorization);
        return response()->json(['data' => $migration->reconciliation($documentMigrationBatch), 'items' => $documentMigrationBatch->items()->get(['id', 'original_filename', 'sha256', 'verification_status', 'verification_notes', 'source_metadata', 'mapped_metadata'])]);
    }

    private function authorizeMigration(Request $request, AuthorizationService $authorization): void
    {
        abort_unless($authorization->allows($request->user(), 'document.register'), 403, 'Only an active KMT registration assignment may manage migration batches.');
    }
}
