<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentVersion;
use App\Models\SubmissionValidationRun;
use App\Services\AuthorizationService;
use App\Services\DocumentVersionStorageService;
use App\Services\DocumentAuditService;
use App\Services\DocumentDistributionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DocumentVersionController extends Controller
{
    public function replaceFiles(Request $request, Document $document, DocumentVersion $documentVersion, AuthorizationService $authorization, DocumentVersionStorageService $storage, DocumentAuditService $audit): JsonResponse
    {
        abort_unless((int) $documentVersion->document_id === (int) $document->id, 404);
        abort_unless($authorization->allows($request->user(), 'document.create_revise', $document->owning_unit_id), 403, 'Your active Process Owner assignment does not include this document unit.');
        $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['required', 'file', 'mimes:pdf,docx,xlsx,jpg,jpeg,png', 'extensions:pdf,docx,xlsx,jpg,jpeg,png', 'max:'.config('app.upload_max_kb', 51200)],
        ]);
        $version = $storage->replaceCurrentFiles($documentVersion, $request->user(), $request->file('files', []), $audit);
        return response()->json(['data' => $this->versionPayload($version)]);
    }

    public function store(Request $request, Document $document, AuthorizationService $authorization, DocumentVersionStorageService $storage, DocumentAuditService $audit): JsonResponse
    {
        abort_unless(
            $authorization->allows($request->user(), 'document.create_revise', $document->owning_unit_id),
            403,
            'Your active Process Owner assignment does not include this document unit.',
        );

        $data = $request->validate([
            'revision_kind' => ['nullable', Rule::in(['minor', 'major'])],
            'change_summary' => ['nullable', 'string', 'max:4000'],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['required', 'file', 'mimes:pdf,docx,xlsx,jpg,jpeg,png', 'extensions:pdf,docx,xlsx,jpg,jpeg,png', 'max:'.config('app.upload_max_kb', 51200)],
        ]);

        $version = $storage->createVersion(
            $document,
            $request->user(),
            $data,
            $request->file('files', []),
            $audit,
        );

        return response()->json(['data' => $this->versionPayload($version)], 201);
    }

    public function download(Request $request, DocumentFile $documentFile, AuthorizationService $authorization, DocumentDistributionService $distributions, DocumentVersionStorageService $storage): mixed
    {
        $version = $documentFile->version()->with('document')->firstOrFail();
        $document = $version->document;
        abort_unless(
            $authorization->allows($request->user(), 'document.registry.view', $document->owning_unit_id)
                || $distributions->canAccessVersion($request->user(), (int) $version->id),
            404,
            'The document file was not found.',
        );

        return $storage->download($documentFile);
    }

    /** @return array<string, mixed> */
    public function versionPayload(DocumentVersion $version): array
    {
        return [
            'id' => $version->id,
            'sequence_no' => $version->sequence_no,
            'revision_label' => $version->revision_label,
            'proposed_effective_date' => $version->proposed_effective_date?->format('Y-m-d'),
            'effective_date' => $version->effective_date?->format('Y-m-d'),
            'status' => $version->status,
            'change_summary' => $version->change_summary,
            'created_at' => $version->created_at?->toISOString(),
            'files' => $version->files->map(fn (DocumentFile $file) => [
                'id' => $file->id,
                'original_filename' => $file->original_filename,
                'media_type' => $file->media_type,
                'byte_size' => $file->byte_size,
                'sha256' => $file->sha256,
                'uploaded_at' => $file->uploaded_at?->toISOString(),
                'is_current' => $file->is_current,
                'validation_runs' => SubmissionValidationRun::query()->where('document_file_id', $file->id)->orderBy('run_number')->get()->map(fn (SubmissionValidationRun $run) => [
                    'run_number' => $run->run_number,
                    'status' => $run->status,
                    'code_source' => $run->code_source,
                    'extracted_code' => $run->extracted_code,
                    'findings' => $run->findings,
                    'checked_at' => $run->checked_at?->toISOString(),
                ])->values(),
            ])->values(),
        ];
    }
}
