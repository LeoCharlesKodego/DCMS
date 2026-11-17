<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Document;
use App\Models\DocumentDistribution;
use App\Models\DocumentMigrationBatch;
use App\Models\OperationalRecord;
use App\Services\AuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function summary(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->authorizeReports($request, $authorization);
        return response()->json(['data' => [
            'documents' => Document::query()->count(),
            'released_versions' => \App\Models\DocumentVersion::query()->where('status', 'released')->count(),
            'distributions' => DocumentDistribution::query()->count(),
            'migration_batches' => DocumentMigrationBatch::query()->count(),
            'operational_records' => OperationalRecord::query()->count(),
            'audit_events' => AuditEvent::query()->count(),
        ]]);
    }

    public function audit(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->authorizeReports($request, $authorization);
        $filters = $request->validate(['event_type' => ['nullable', 'string', 'max:120'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'limit' => ['nullable', 'integer', 'min:1', 'max:200']]);
        $query = AuditEvent::query()->with(['actor:id,name', 'document:id,title'])->orderByDesc('occurred_at');
        if (! empty($filters['event_type'])) $query->where('event_type', $filters['event_type']);
        if (! empty($filters['from'])) $query->whereDate('occurred_at', '>=', $filters['from']);
        if (! empty($filters['to'])) $query->whereDate('occurred_at', '<=', $filters['to']);
        return response()->json(['data' => $query->limit($filters['limit'] ?? 100)->get()]);
    }

    private function authorizeReports(Request $request, AuthorizationService $authorization): void
    {
        abort_unless($authorization->allows($request->user(), 'document.register') || $authorization->allows($request->user(), 'records.manage'), 403, 'An active KMT document-register or records-management assignment is required.');
    }
}
