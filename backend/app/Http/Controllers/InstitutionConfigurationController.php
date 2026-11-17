<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use App\Models\OrganizationalUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InstitutionConfigurationController extends Controller
{
    public function showInstitution(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        return response()->json(['data' => Institution::query()->findOrFail(1)]);
    }

    public function updateInstitution(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:80'],
            'contact_details' => ['nullable', 'string', 'max:2000'],
            'facility_type' => ['nullable', 'string', 'max:120'],
            'timezone' => ['nullable', 'timezone'],
            'status' => ['required', 'in:active,inactive'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $institution = Institution::query()->findOrFail(1);
        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('institution-logos', 'local');
        }
        unset($data['logo']);
        $institution->update($data);

        return response()->json(['data' => $institution->refresh()]);
    }

    public function institutionLogo(Request $request): StreamedResponse
    {
        $this->authorizeAdmin($request);
        $path = Institution::query()->findOrFail(1)->logo_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    public function indexUnits(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        return response()->json([
            'data' => OrganizationalUnit::query()->with('parent:id,name')->orderBy('name')->get()
                ->map(fn (OrganizationalUnit $unit) => $this->unitPayload($unit)),
        ]);
    }

    public function storeUnit(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:organizational_units,id'],
            'name' => ['required', 'string', 'max:180'],
            'unit_type' => ['required', 'string', 'max:80'],
        ]);
        $this->validateParent($data['parent_id'] ?? null);
        $unit = OrganizationalUnit::query()->create($data + ['is_active' => true]);

        return response()->json(['data' => $this->unitPayload($unit->load('parent:id,name'))], 201);
    }

    public function updateUnit(Request $request, OrganizationalUnit $organizationalUnit): JsonResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:organizational_units,id'],
            'name' => ['required', 'string', 'max:180'],
            'unit_type' => ['required', 'string', 'max:80'],
            'is_active' => ['required', 'boolean'],
        ]);

        $parentId = array_key_exists('parent_id', $data) ? $data['parent_id'] : $organizationalUnit->parent_id;
        $this->validateParent($parentId, $organizationalUnit);
        if (! $data['is_active'] && $organizationalUnit->children()->where('is_active', true)->exists()) {
            throw ValidationException::withMessages([
                'is_active' => 'Move or deactivate active child units before deactivating this unit.',
            ]);
        }

        $organizationalUnit->update($data);

        return response()->json(['data' => $this->unitPayload($organizationalUnit->refresh()->load('parent:id,name'))]);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->is_system_admin, 403, 'System administrator access is required.');
    }

    private function validateParent(?int $parentId, ?OrganizationalUnit $unit = null): void
    {
        if ($parentId === null) {
            return;
        }

        $parent = OrganizationalUnit::query()->findOrFail($parentId);
        if (! $parent->is_active) {
            throw ValidationException::withMessages(['parent_id' => 'Choose an active parent unit.']);
        }

        $ancestor = $parent;
        while ($ancestor !== null) {
            if ($unit !== null && $ancestor->is($unit)) {
                throw ValidationException::withMessages(['parent_id' => 'A unit cannot be its own parent or a descendant of itself.']);
            }
            $ancestor = $ancestor->parent;
        }
    }

    private function unitPayload(OrganizationalUnit $unit): array
    {
        return [
            'id' => $unit->id,
            'parent_id' => $unit->parent_id,
            'parent_name' => $unit->parent?->name,
            'name' => $unit->name,
            'unit_type' => $unit->unit_type,
            'is_active' => $unit->is_active,
        ];
    }
}
