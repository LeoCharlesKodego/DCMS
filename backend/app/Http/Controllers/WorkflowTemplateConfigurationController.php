<?php

namespace App\Http\Controllers;

use App\Models\DocumentCategory;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use App\Models\WorkflowTemplate;
use App\Models\WorkflowTemplateVersion;
use App\Services\DocumentAuditService;
use App\Services\WorkflowTemplateConfigurationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WorkflowTemplateConfigurationController extends Controller
{
    public function index(Request $request, WorkflowTemplateConfigurationService $configuration): JsonResponse
    {
        $this->requireAdministrator($request);

        return response()->json([
            'data' => WorkflowTemplate::query()->with([
                'versions.documentType:id,name,code',
                'versions.category:id,name',
                'versions.organizationalUnit:id,name',
                'versions.stages.eligibleRoles:id,code,name',
            ])->orderBy('name')->get(),
            'document_types' => DocumentType::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'categories' => DocumentCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'organizational_units' => OrganizationalUnit::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'unit_type']),
            'eligible_roles' => $configuration->eligibleRoleOptions(),
        ]);
    }

    public function store(Request $request, WorkflowTemplateConfigurationService $configuration, DocumentAuditService $audit): JsonResponse
    {
        $this->requireAdministrator($request);
        $data = $this->validatedTemplate($request, true);
        $this->validateStageRolePermissions($data['stages'], $configuration);

        $template = DB::transaction(function () use ($data, $request, $audit): WorkflowTemplate {
            $template = WorkflowTemplate::query()->create([
                'template_key' => $data['template_key'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_active' => true,
                'created_by_user_id' => $request->user()->id,
            ]);
            $version = $template->versions()->create([
                'version_no' => 1,
                'document_type_id' => $data['document_type_id'],
                'category_id' => $data['category_id'] ?? null,
                'organizational_unit_id' => $data['organizational_unit_id'] ?? null,
                'trigger_event' => 'version.submitted',
                'status' => 'draft',
            ]);
            $this->persistStages($version, $data['stages']);
            $audit->record('workflow_template', $template->id, null, $request->user(), 'configuration.workflow_template.created', null, 'active');
            $audit->record('workflow_template_version', $version->id, null, $request->user(), 'configuration.workflow_template_version.created', null, 'draft', null, [
                'version_no' => $version->version_no,
                'stage_count' => count($data['stages']),
                'document_type_id' => $version->document_type_id,
            ]);

            return $template;
        });

        return response()->json(['data' => $this->loadTemplate($template)], 201);
    }

    public function storeVersion(Request $request, WorkflowTemplate $workflowTemplate, WorkflowTemplateConfigurationService $configuration, DocumentAuditService $audit): JsonResponse
    {
        $this->requireAdministrator($request);
        abort_unless($workflowTemplate->is_active, 409, 'Create a new active workflow template before adding a version.');
        $data = $this->validatedTemplate($request, false);
        $this->validateStageRolePermissions($data['stages'], $configuration);

        $version = DB::transaction(function () use ($data, $request, $workflowTemplate, $audit): WorkflowTemplateVersion {
            $template = WorkflowTemplate::query()->whereKey($workflowTemplate->id)->lockForUpdate()->firstOrFail();
            abort_unless($template->is_active, 409, 'This workflow template is inactive.');
            $nextVersion = ((int) $template->versions()->max('version_no')) + 1;
            $version = $template->versions()->create([
                'version_no' => $nextVersion,
                'document_type_id' => $data['document_type_id'],
                'category_id' => $data['category_id'] ?? null,
                'organizational_unit_id' => $data['organizational_unit_id'] ?? null,
                'trigger_event' => 'version.submitted',
                'status' => 'draft',
            ]);
            $this->persistStages($version, $data['stages']);
            $audit->record('workflow_template_version', $version->id, null, $request->user(), 'configuration.workflow_template_version.created', null, 'draft', null, [
                'version_no' => $version->version_no,
                'stage_count' => count($data['stages']),
                'document_type_id' => $version->document_type_id,
            ]);

            return $version;
        });

        return response()->json(['data' => $this->loadTemplate($workflowTemplate->refresh())->versions->firstWhere('id', $version->id)], 201);
    }

    public function publish(Request $request, WorkflowTemplateVersion $workflowTemplateVersion, WorkflowTemplateConfigurationService $configuration, DocumentAuditService $audit): JsonResponse
    {
        $this->requireAdministrator($request);

        DB::transaction(function () use ($request, $workflowTemplateVersion, $configuration, $audit): void {
            $version = WorkflowTemplateVersion::query()
                ->with('stages.eligibleRoles.permissions')
                ->whereKey($workflowTemplateVersion->id)
                ->lockForUpdate()
                ->firstOrFail();
            $template = WorkflowTemplate::query()->whereKey($version->workflow_template_id)->lockForUpdate()->firstOrFail();
            abort_unless($template->is_active, 409, 'Activate the workflow template before publishing a version.');
            abort_unless($version->status === 'draft', 409, 'Only a draft workflow version can be published. Create a new version to make changes.');
            abort_unless((int) $template->versions()->max('version_no') === (int) $version->version_no, 409, 'Only the latest draft version can be published.');

            $configuration->assertPublishable($version);
            $this->assertNoOverlappingPublishedTemplate($version);

            $previousVersions = $template->versions()->where('status', 'published')->lockForUpdate()->get();
            foreach ($previousVersions as $previousVersion) {
                $previousVersion->update(['status' => 'retired']);
                $audit->record('workflow_template_version', $previousVersion->id, null, $request->user(), 'configuration.workflow_template_version.retired', 'published', 'retired');
            }

            $version->update([
                'status' => 'published',
                'published_by_user_id' => $request->user()->id,
                'published_at' => now(),
            ]);
            $audit->record('workflow_template_version', $version->id, null, $request->user(), 'configuration.workflow_template_version.published', 'draft', 'published');
        });

        return response()->json(['data' => $this->loadTemplate($workflowTemplateVersion->template()->firstOrFail())]);
    }

    public function updateTemplate(Request $request, WorkflowTemplate $workflowTemplate, DocumentAuditService $audit): JsonResponse
    {
        $this->requireAdministrator($request);
        $data = $request->validate(['is_active' => ['required', 'boolean']]);

        DB::transaction(function () use ($request, $workflowTemplate, $data, $audit): void {
            $template = WorkflowTemplate::query()->whereKey($workflowTemplate->id)->lockForUpdate()->firstOrFail();
            $previousStatus = $template->is_active ? 'active' : 'inactive';
            if ($data['is_active'] && ! $template->is_active) {
                foreach ($template->versions()->where('status', 'published')->get() as $publishedVersion) {
                    $this->assertNoOverlappingPublishedTemplate($publishedVersion);
                }
            }
            $template->update(['is_active' => $data['is_active']]);
            $nextStatus = $template->is_active ? 'active' : 'inactive';
            if ($previousStatus !== $nextStatus) {
                $audit->record('workflow_template', $template->id, null, $request->user(), 'configuration.workflow_template.status_changed', $previousStatus, $nextStatus);
            }
        });

        return response()->json(['data' => $this->loadTemplate($workflowTemplate->refresh())]);
    }

    /** @return array<string, mixed> */
    private function validatedTemplate(Request $request, bool $newTemplate): array
    {
        $rules = [
            'name' => [$newTemplate ? 'required' : 'sometimes', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:4000'],
            'document_type_id' => ['required', 'integer', Rule::exists('document_types', 'id')->where('is_active', true)],
            'category_id' => ['nullable', 'integer', Rule::exists('document_categories', 'id')->where('is_active', true)],
            'organizational_unit_id' => ['nullable', 'integer', Rule::exists('organizational_units', 'id')->where('is_active', true)],
            'stages' => ['required', 'array', 'min:1', 'max:40'],
            'stages.*.stage_key' => ['required', 'string', 'max:64', 'distinct'],
            'stages.*.name' => ['required', 'string', 'max:180'],
            'stages.*.purpose' => ['nullable', 'string', 'max:4000'],
            'stages.*.stage_order' => ['required', 'integer', 'min:1', 'max:65535'],
            'stages.*.parallel_group' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'stages.*.action_type' => ['required', Rule::in(['verification', 'review', 'recommendation', 'approval', 'release'])],
            'stages.*.is_mandatory' => ['required', 'boolean'],
            'stages.*.required_decisions' => ['required', 'integer', Rule::in([1])],
            'stages.*.roles' => ['required', 'array', 'min:1', 'max:20'],
            'stages.*.roles.*.role_id' => ['required', 'integer', 'distinct', 'exists:roles,id'],
            'stages.*.roles.*.scope_type' => ['required', Rule::in(['institution', 'organizational_unit'])],
        ];
        if ($newTemplate) {
            $rules['template_key'] = ['required', 'string', 'alpha_dash', 'max:64', 'unique:workflow_templates,template_key'];
        }

        return $request->validate($rules);
    }

    /** @param list<array<string, mixed>> $stages */
    private function validateStageRolePermissions(array $stages, WorkflowTemplateConfigurationService $configuration): void
    {
        foreach ($stages as $index => $stage) {
            $configuration->validateStageRoles($stage, $index);
        }
    }

    /** @param list<array<string, mixed>> $stages */
    private function persistStages(WorkflowTemplateVersion $version, array $stages): void
    {
        foreach ($stages as $stageData) {
            $stage = $version->stages()->create([
                'stage_key' => $stageData['stage_key'],
                'name' => $stageData['name'],
                'purpose' => $stageData['purpose'] ?? null,
                'stage_order' => $stageData['stage_order'],
                'parallel_group' => $stageData['parallel_group'] ?? null,
                'action_type' => $stageData['action_type'],
                'is_mandatory' => $stageData['is_mandatory'],
                'required_decisions' => $stageData['required_decisions'],
            ]);

            foreach ($stageData['roles'] as $roleData) {
                $stage->eligibleRoles()->attach($roleData['role_id'], ['scope_type' => $roleData['scope_type']]);
            }
        }
    }

    private function assertNoOverlappingPublishedTemplate(WorkflowTemplateVersion $version): void
    {
        $candidates = WorkflowTemplateVersion::query()
            ->where('id', '<>', $version->id)
            ->where('workflow_template_id', '<>', $version->workflow_template_id)
            ->where('document_type_id', $version->document_type_id)
            ->where('trigger_event', $version->trigger_event)
            ->where('status', 'published')
            ->whereHas('template', fn (Builder $query) => $query->where('is_active', true))
            ->get();

        $overlap = $candidates->first(fn (WorkflowTemplateVersion $candidate) =>
            ($candidate->category_id === null || $version->category_id === null || (int) $candidate->category_id === (int) $version->category_id)
            && ($candidate->organizational_unit_id === null || $version->organizational_unit_id === null || (int) $candidate->organizational_unit_id === (int) $version->organizational_unit_id)
        );

        if ($overlap) {
            throw ValidationException::withMessages(['document_type_id' => 'Another active published template already covers this document type, category, and unit scope. Retire or deactivate it first.']);
        }
    }

    private function loadTemplate(WorkflowTemplate $template): WorkflowTemplate
    {
        return $template->load([
            'versions.documentType:id,name,code',
            'versions.category:id,name',
            'versions.organizationalUnit:id,name',
            'versions.stages.eligibleRoles:id,code,name',
        ]);
    }

    private function requireAdministrator(Request $request): void
    {
        abort_unless($request->user()?->is_system_admin, 403, 'System administrator access is required to configure workflow templates.');
    }
}
