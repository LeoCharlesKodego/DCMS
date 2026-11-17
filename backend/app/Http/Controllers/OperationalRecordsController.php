<?php

namespace App\Http\Controllers;

use App\Models\DispositionRequest;
use App\Models\OperationalRecord;
use App\Models\RetentionSchedule;
use App\Services\AuthorizationService;
use App\Services\OperationalRecordsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OperationalRecordsController extends Controller
{
    public function index(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->authorizeRecords($request, $authorization);
        return response()->json([
            'records' => OperationalRecord::query()->with(['schedule', 'holds', 'dispositionRequests'])->orderByDesc('created_at')->get(),
            'schedules' => RetentionSchedule::query()->withCount('records')->orderBy('record_type')->get(),
            'disposition_requests' => DispositionRequest::query()->with('record:id,title,status')->orderByDesc('created_at')->get(),
        ]);
    }

    public function storeSchedule(Request $request, AuthorizationService $authorization, OperationalRecordsService $records): JsonResponse
    {
        $this->authorizeRecords($request, $authorization);
        $data = $request->validate([
            'record_type' => ['required', 'string', 'max:255'], 'retention_duration' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'retention_unit' => ['nullable', Rule::in(['days', 'months', 'years'])], 'trigger_event' => ['required', 'string', 'max:100'],
            'legal_basis' => ['nullable', 'string', 'max:4000'], 'responsible_unit_id' => ['nullable', 'integer', 'exists:organizational_units,id'],
            'disposition_method' => ['nullable', 'string', 'max:255'], 'approval_authority' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        return response()->json(['data' => $records->createSchedule($request->user(), $data)], 201);
    }

    public function storeRecord(Request $request, AuthorizationService $authorization, OperationalRecordsService $records): JsonResponse
    {
        $this->authorizeRecords($request, $authorization);
        $data = $request->validate([
            'record_type' => ['required', 'string', 'max:255'], 'title' => ['required', 'string', 'max:500'],
            'reference_number' => ['nullable', 'string', 'max:255'], 'retention_schedule_id' => ['nullable', 'integer', 'exists:retention_schedules,id'],
            'owning_unit_id' => ['nullable', 'integer', 'exists:organizational_units,id'], 'created_on' => ['nullable', 'date'],
            'retention_triggered_on' => ['nullable', 'date'],
        ]);
        return response()->json(['data' => $records->createRecord($request->user(), $data)], 201);
    }

    public function hold(Request $request, OperationalRecord $operationalRecord, AuthorizationService $authorization, OperationalRecordsService $records): JsonResponse
    {
        $this->authorizeRecords($request, $authorization);
        $data = $request->validate(['reason' => ['required', 'string', 'max:4000'], 'authority_reference' => ['nullable', 'string', 'max:500']]);
        $records->placeHold($operationalRecord, $request->user(), $data['reason'], $data['authority_reference'] ?? null);
        return response()->json(['data' => $operationalRecord->fresh('holds')], 201);
    }

    public function releaseHold(Request $request, OperationalRecord $operationalRecord, int $hold, AuthorizationService $authorization, OperationalRecordsService $records): JsonResponse
    {
        $this->authorizeRecords($request, $authorization);
        $data = $request->validate(['reason' => ['required', 'string', 'max:4000']]);
        $records->releaseHold($operationalRecord, $request->user(), $hold, $data['reason']);
        return response()->json(['data' => $operationalRecord->fresh('holds')]);
    }

    public function requestDisposition(Request $request, OperationalRecord $operationalRecord, AuthorizationService $authorization, OperationalRecordsService $records): JsonResponse
    {
        $this->authorizeRecords($request, $authorization);
        $data = $request->validate(['reason' => ['required', 'string', 'max:4000']]);
        return response()->json(['data' => $records->requestDisposition($operationalRecord, $request->user(), $data['reason'])], 201);
    }

    public function decideDisposition(Request $request, DispositionRequest $dispositionRequest, AuthorizationService $authorization, OperationalRecordsService $records): JsonResponse
    {
        $this->authorizeRecords($request, $authorization);
        $data = $request->validate(['decision' => ['required', Rule::in(['approved', 'rejected', 'defer'])], 'reason' => ['required', 'string', 'max:4000']]);
        return response()->json(['data' => $records->decide($dispositionRequest, $request->user(), $data['decision'], $data['reason'])]);
    }

    public function executeDisposition(Request $request, DispositionRequest $dispositionRequest, AuthorizationService $authorization, OperationalRecordsService $records): JsonResponse
    {
        $this->authorizeRecords($request, $authorization);
        $data = $request->validate(['execution_evidence' => ['required', 'string', 'max:4000']]);
        return response()->json(['data' => $records->execute($dispositionRequest, $request->user(), $data['execution_evidence'])]);
    }

    private function authorizeRecords(Request $request, AuthorizationService $authorization): void
    {
        abort_unless($authorization->allows($request->user(), 'records.manage'), 403, 'Only an active KMT records-management assignment may perform this action.');
    }
}
