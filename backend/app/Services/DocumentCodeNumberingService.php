<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentCodeComponent;
use App\Models\DocumentCodeSequence;
use App\Models\Institution;
use App\Models\MasterCodeRegisterEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentCodeNumberingService
{
    public function assign(Document $document, User $assignedBy, array $components, DocumentAuditService $audit): MasterCodeRegisterEntry
    {
        if ($document->masterCodeRegisterEntry()->exists() || $document->status !== 'pending_code') {
            throw ValidationException::withMessages(['document' => 'This document request already has a code or is no longer awaiting code assignment.']);
        }

        $service = $this->component($components['service_component_id'], 'service');
        $section = $this->component($components['section_component_id'], 'section_unit');
        $documentType = $this->component($components['document_type_component_id'], 'document_type');
        $unit = isset($components['unit_component_id'])
            ? $this->component($components['unit_component_id'], 'section_unit')
            : null;

        if ($section->parent_service_id !== $service->id || ($unit && $unit->parent_service_id !== $service->id)) {
            throw ValidationException::withMessages(['code_components' => 'Choose section and unit codes listed under the selected service.']);
        }
        if ($unit && $section->is($unit)) {
            throw ValidationException::withMessages(['unit_component_id' => 'The same source code cannot be used for both section and unit components.']);
        }

        $institution = Institution::query()->findOrFail(1);
        if (! $institution->short_name) {
            throw ValidationException::withMessages(['institution' => 'Set the institution short name before assigning document codes.']);
        }

        return DB::transaction(function () use ($document, $assignedBy, $audit, $service, $section, $unit, $documentType, $institution): MasterCodeRegisterEntry {
            $prefixParts = [$institution->short_name, $service->code, $section->code];
            if ($unit) {
                $prefixParts[] = $unit->code;
            }
            $prefixParts[] = $documentType->code;
            $prefix = implode('-', $prefixParts);

            $now = now();
            DB::table('document_code_sequences')->insertOrIgnore([
                'code_prefix' => $prefix,
                'next_sequence' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $counter = DocumentCodeSequence::query()->where('code_prefix', $prefix)->lockForUpdate()->firstOrFail();
            $sequence = (int) $counter->next_sequence;
            if ($sequence < 1 || $sequence > 999) {
                throw ValidationException::withMessages(['sequence' => 'The three-digit sequence for this code prefix is exhausted.']);
            }

            $baseCode = $prefix.'-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
            $entry = MasterCodeRegisterEntry::query()->create([
                'document_id' => $document->id,
                'service_component_id' => $service->id,
                'section_component_id' => $section->id,
                'unit_component_id' => $unit?->id,
                'document_type_component_id' => $documentType->id,
                'code_prefix' => $prefix,
                'sequence_number' => $sequence,
                'base_code' => $baseCode,
                'requested_by_user_id' => $document->owner_user_id,
                'assigned_by_user_id' => $assignedBy->id,
                'requested_at' => $document->created_at,
                'assigned_at' => $now,
            ]);

            $counter->update(['next_sequence' => $sequence + 1]);
            $document->update([
                'status' => 'registered',
                'registered_by_user_id' => $assignedBy->id,
                'registered_at' => $now,
            ]);
            $audit->record('master_code_register_entry', $entry->id, $document, $assignedBy, 'code.assigned', 'pending_code', 'registered', null, ['base_code' => $baseCode]);

            return $entry->load(['serviceComponent', 'sectionComponent', 'unitComponent', 'documentTypeComponent']);
        });
    }

    private function component(int $id, string $type): DocumentCodeComponent
    {
        return DocumentCodeComponent::query()
            ->whereKey($id)
            ->where('component_type', $type)
            ->where('is_active', true)
            ->where('is_conflicted', false)
            ->firstOrFail();
    }
}
