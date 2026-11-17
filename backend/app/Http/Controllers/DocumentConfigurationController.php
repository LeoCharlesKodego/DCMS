<?php

namespace App\Http\Controllers;

use App\Models\DocumentCategory;
use App\Models\DocumentType;
use App\Services\DocumentAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentConfigurationController extends Controller
{
    public function indexTypes(): JsonResponse
    {
        return response()->json([
            'data' => DocumentType::query()->orderBy('name')->get([
                'id', 'code', 'name', 'description', 'is_active',
                'approval_flow_profile',
            ]),
        ]);
    }

    public function storeType(Request $request, DocumentAuditService $audit): JsonResponse
    {
        $this->authorizeConfiguration($request);

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'approval_flow_profile' => ['sometimes', 'required', 'string', 'in:source_defined,phase4b_manual_sop_guideline,deferred'],
        ]);

        $type = DB::transaction(function () use ($data, $request, $audit): DocumentType {
            $type = DocumentType::query()->create($data + ['is_active' => true]);
            $audit->record('document_type', $type->id, null, $request->user(), 'configuration.document_type.created');

            return $type;
        });

        return response()->json(['data' => $type], 201);
    }

    public function updateType(Request $request, DocumentType $documentType, DocumentAuditService $audit): JsonResponse
    {
        $this->authorizeConfiguration($request);

        $data = $request->validate([
            'code' => ['sometimes', 'nullable', 'string', 'max:255'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'approval_flow_profile' => ['sometimes', 'required', 'string', 'in:source_defined,phase4b_manual_sop_guideline,deferred'],
        ]);

        DB::transaction(function () use ($data, $request, $documentType, $audit): void {
            $oldActive = $documentType->is_active;
            $documentType->fill($data)->save();
            $audit->record('document_type', $documentType->id, null, $request->user(), 'configuration.document_type.updated', (string) (int) $oldActive, (string) (int) $documentType->is_active, null, ['changed_fields' => implode(',', array_keys($data))]);
        });

        return response()->json(['data' => $documentType->refresh()]);
    }

    public function indexCategories(): JsonResponse
    {
        return response()->json([
            'data' => DocumentCategory::query()->orderBy('name')->get([
                'id', 'parent_id', 'name', 'description', 'is_active',
            ]),
        ]);
    }

    public function storeCategory(Request $request, DocumentAuditService $audit): JsonResponse
    {
        $this->authorizeConfiguration($request);

        $data = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:document_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $this->validateParent($data['parent_id'] ?? null);

        $category = DB::transaction(function () use ($data, $request, $audit): DocumentCategory {
            $category = DocumentCategory::query()->create($data + ['is_active' => true]);
            $audit->record('document_category', $category->id, null, $request->user(), 'configuration.document_category.created');

            return $category;
        });

        return response()->json(['data' => $category], 201);
    }

    public function updateCategory(Request $request, DocumentCategory $documentCategory, DocumentAuditService $audit): JsonResponse
    {
        $this->authorizeConfiguration($request);

        $data = $request->validate([
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:document_categories,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $parentId = array_key_exists('parent_id', $data) ? $data['parent_id'] : $documentCategory->parent_id;
        $this->validateParent($parentId, $documentCategory);

        if (array_key_exists('is_active', $data) && ! filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN) && $documentCategory->children()->where('is_active', true)->exists()) {
            throw ValidationException::withMessages([
                'is_active' => 'Move or deactivate active child categories before deactivating this category.',
            ]);
        }

        DB::transaction(function () use ($data, $request, $documentCategory, $audit): void {
            $oldActive = $documentCategory->is_active;
            $documentCategory->fill($data)->save();
            $audit->record('document_category', $documentCategory->id, null, $request->user(), 'configuration.document_category.updated', (string) (int) $oldActive, (string) (int) $documentCategory->is_active, null, ['changed_fields' => implode(',', array_keys($data))]);
        });

        return response()->json(['data' => $documentCategory->refresh()]);
    }

    private function authorizeConfiguration(Request $request): void
    {
        abort_unless($request->user()?->is_system_admin, 403, 'System administrator access is required to change document configuration.');
    }

    private function validateParent(?int $parentId, ?DocumentCategory $category = null): void
    {
        if ($parentId === null) {
            return;
        }

        $parent = DocumentCategory::query()->findOrFail($parentId);

        if (! $parent->is_active) {
            throw ValidationException::withMessages([
                'parent_id' => 'Choose an active parent category.',
            ]);
        }

        while ($category !== null && $parent !== null) {
            if ($parent->is($category)) {
                throw ValidationException::withMessages([
                    'parent_id' => 'A category cannot be its own parent or a descendant of itself.',
                ]);
            }

            $parent = $parent->parent;
        }
    }
}
