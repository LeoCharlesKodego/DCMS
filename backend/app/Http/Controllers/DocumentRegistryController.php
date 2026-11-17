<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentCodeComponent;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use App\Models\MasterCodeRegisterEntry;
use App\Models\User;
use App\Models\DocumentFile;
use App\Services\AuthorizationService;
use App\Services\DocumentCodeNumberingService;
use App\Services\DocumentAuditService;
use App\Services\DocumentLifecycleService;
use App\Services\WorkflowDecisionService;
use App\Services\DocumentDistributionService;
use App\Services\SubmissionValidationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DocumentRegistryController extends Controller
{
    public function index(Request $request, AuthorizationService $authorization, WorkflowDecisionService $workflowDecisions, DocumentDistributionService $distributions): JsonResponse
    {
        $user = $request->user();
        $canViewInstitution = $authorization->allows($user, 'document.registry.view');
        $viewUnits = $authorization->organizationalUnitIdsFor($user, 'document.registry.view');
        abort_unless($canViewInstitution || $viewUnits !== [], 403, 'No active ONP role grants access to the document register.');

        $query = Document::query()->with([
            'documentType:id,name', 'category:id,name', 'owner:id,name', 'owningUnit:id,name',
            'masterCodeRegisterEntry:id,document_id,base_code,sequence_number,assigned_at,document_type_component_id',
            'masterCodeRegisterEntry.documentTypeComponent:id,code,name',
            'versions.files',
            'auditEvents.actor:id,name',
        ])->orderByDesc('created_at');
        if (! $canViewInstitution) {
            $query->whereIn('owning_unit_id', $viewUnits);
        }

        $documents = $query->get()->map(fn (Document $document) => $this->documentPayload($document, $user, $workflowDecisions, $distributions));
        $createUnits = $authorization->organizationalUnitIdsFor($user, 'document.create_revise');

        return response()->json([
            'data' => $documents,
            'can_assign_code' => $authorization->allows($user, 'document.code.assign'),
            'can_distribute' => $authorization->allows($user, 'document.distribute'),
            'can_view_master_code_register' => $authorization->allows($user, 'document.register'),
            'create_units' => OrganizationalUnit::query()->whereIn('id', $createUnits)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'unit_type']),
            'document_types' => DocumentType::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'categories' => DocumentCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function codeComponents(Request $request): JsonResponse
    {
        abort_unless(app(AuthorizationService::class)->allows($request->user(), 'document.code.assign'), 403);

        $components = DocumentCodeComponent::query()
            ->where('is_active', true)
            ->where('is_conflicted', false)
            ->orderBy('code')
            ->get(['id', 'parent_service_id', 'component_type', 'code', 'name', 'source_group']);

        return response()->json([
            'services' => $components->where('component_type', 'service')->values(),
            'section_units' => $components->where('component_type', 'section_unit')->values(),
            'document_types' => $components->where('component_type', 'document_type')->values(),
        ]);
    }

    public function masterCodeRegister(Request $request): JsonResponse
    {
        abort_unless(app(AuthorizationService::class)->allows($request->user(), 'document.register'), 403, 'Only an active KMT role assignment may view the Master Code Register.');

        $entries = MasterCodeRegisterEntry::query()->with([
            'document:id,title,current_revision_label,owning_unit_id',
            'document.owningUnit:id,name',
            'serviceComponent:id,code,name',
            'sectionComponent:id,code,name',
            'unitComponent:id,code,name',
            'documentTypeComponent:id,code,name',
            'assignedBy:id,name',
        ])->orderBy('assigned_at')->get();

        return response()->json(['data' => $entries]);
    }

    public function store(Request $request, DocumentAuditService $audit, WorkflowDecisionService $workflowDecisions, DocumentDistributionService $distributions): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'document_type_id' => ['required', 'integer', Rule::exists('document_types', 'id')->where('is_active', true)],
            'category_id' => ['nullable', 'integer', Rule::exists('document_categories', 'id')->where('is_active', true)],
            'classification_id' => ['nullable', 'integer', Rule::exists('document_classifications', 'id')->where('is_active', true)],
            'owning_unit_id' => ['required', 'integer', Rule::exists('organizational_units', 'id')->where('is_active', true)],
            'retention_years' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'remarks' => ['nullable', 'string', 'max:4000'],
        ]);
        abort_unless(app(AuthorizationService::class)->allows($request->user(), 'document.create_revise', (int) $data['owning_unit_id']), 403, 'Your Process Owner assignment does not include this unit.');

        $document = DB::transaction(function () use ($data, $request, $audit): Document {
            $document = Document::query()->create($data + [
                'owner_user_id' => $request->user()->id,
                'created_by_user_id' => $request->user()->id,
                'current_revision_label' => '1.0',
                'status' => 'pending_code',
            ]);
            $audit->record('document', $document->id, $document, $request->user(), 'document.created', null, 'pending_code');

            return $document;
        });

        return response()->json(['data' => $this->documentPayload($document->load(['documentType', 'category', 'owner', 'owningUnit']), $request->user(), $workflowDecisions, $distributions)], 201);
    }

    public function assignCode(Request $request, Document $document, DocumentCodeNumberingService $numbering, DocumentAuditService $audit, WorkflowDecisionService $workflowDecisions, DocumentDistributionService $distributions, SubmissionValidationService $validation): JsonResponse
    {
        abort_unless(app(AuthorizationService::class)->allows($request->user(), 'document.code.assign'), 403, 'Only an active KMT role assignment may assign a code.');
        $data = $request->validate([
            'service_component_id' => ['required', 'integer', 'exists:document_code_components,id'],
            'section_component_id' => ['required', 'integer', 'exists:document_code_components,id'],
            'unit_component_id' => ['nullable', 'integer', 'exists:document_code_components,id'],
            'document_type_component_id' => ['required', 'integer', 'exists:document_code_components,id'],
        ]);

        $entry = $numbering->assign($document, $request->user(), $data, $audit);
        DocumentFile::query()->where('is_current', true)->whereHas('version', fn ($query) => $query->where('document_id', $document->id))->get()->each(fn (DocumentFile $file) => $validation->validate($file, $request->user()));

        return response()->json([
            'data' => $this->documentPayload($document->refresh()->load(['documentType', 'category', 'owner', 'owningUnit', 'masterCodeRegisterEntry.documentTypeComponent']), $request->user(), $workflowDecisions, $distributions),
            'master_code_register_entry' => $entry,
        ]);
    }

    private function documentPayload(Document $document, User $viewer, WorkflowDecisionService $workflowDecisions, DocumentDistributionService $distributions): array
    {
        $baseCode = $document->masterCodeRegisterEntry?->base_code;

        return [
            'id' => $document->id,
            'title' => $document->title,
            'document_type' => $document->documentType?->name,
            'category' => $document->category?->name,
            'owner' => $document->owner?->name,
            'owning_unit' => $document->owningUnit?->name,
            'owning_unit_id' => $document->owning_unit_id,
            'base_code' => $baseCode,
            'display_code' => $baseCode ? $baseCode.' Rev '.$document->current_revision_label : null,
            'revision_label' => $document->current_revision_label,
            'status' => $document->status,
            'effective_date' => $document->effective_date?->format('Y-m-d'),
            'retention_years' => $document->retention_years,
            'remarks' => $document->remarks,
            'registered_at' => $document->registered_at?->toISOString(),
            'versions' => $document->versions->map(fn ($version) => [
                'id' => $version->id,
                'revision_label' => $version->revision_label,
                'proposed_effective_date' => $version->proposed_effective_date?->format('Y-m-d'),
                'effective_date' => $version->effective_date?->format('Y-m-d'),
                'status' => $version->status,
                'change_summary' => $version->change_summary,
                'created_at' => $version->created_at?->toISOString(),
                'available_actions' => app(DocumentLifecycleService::class)->availableActions($version, $viewer),
                'lifecycle_note' => app(DocumentLifecycleService::class)->statusNote($version),
                'workflow_tasks' => $workflowDecisions->visibleTasksFor($viewer, $version),
                'distributions' => $distributions->visibleFor($version, $viewer),
                'files' => $version->files->map(fn ($file) => [
                    'id' => $file->id,
                    'original_filename' => $file->original_filename,
                    'media_type' => $file->media_type,
                    'byte_size' => $file->byte_size,
                    'sha256' => $file->sha256,
                    'uploaded_at' => $file->uploaded_at?->toISOString(),
                    'is_current' => $file->is_current,
                    'validation_runs' => \App\Models\SubmissionValidationRun::query()->where('document_file_id', $file->id)->orderBy('run_number')->get()->map(fn ($run) => [
                        'run_number' => $run->run_number, 'status' => $run->status, 'code_source' => $run->code_source,
                        'extracted_code' => $run->extracted_code, 'findings' => $run->findings, 'checked_at' => $run->checked_at?->toISOString(),
                    ])->values(),
                ])->values(),
            ])->values(),
            'audit_events' => $document->auditEvents->map(fn ($event) => [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'target_type' => $event->target_type,
                'from_state' => $event->from_state,
                'to_state' => $event->to_state,
                'actor' => $event->actor?->name,
                'reason' => $event->reason,
                'occurred_at' => $event->occurred_at?->toISOString(),
            ])->values(),
        ];
    }
}
