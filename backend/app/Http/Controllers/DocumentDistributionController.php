<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\DistributionRecipient;
use App\Services\AuthorizationService;
use App\Services\DocumentDistributionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DocumentDistributionController extends Controller
{
    public function options(Request $request, AuthorizationService $authorization, DocumentDistributionService $distributions): JsonResponse
    {
        abort_unless($authorization->allows($request->user(), 'document.distribute'), 403, 'Only an active KMT distribution assignment may select targets.');

        return response()->json(['targets' => $distributions->targetOptions()]);
    }

    public function inbox(Request $request, DocumentDistributionService $distributions): JsonResponse
    {
        return response()->json(['data' => $distributions->inboxFor($request->user())]);
    }

    public function store(
        Request $request,
        Document $document,
        DocumentVersion $documentVersion,
        AuthorizationService $authorization,
        DocumentDistributionService $distributions,
    ): JsonResponse {
        abort_unless((int) $documentVersion->document_id === (int) $document->id, 404, 'The document version was not found.');
        abort_unless($authorization->allows($request->user(), 'document.distribute'), 403, 'Only an active KMT distribution assignment may issue a document.');

        $data = $request->validate([
            'acknowledgment_required' => ['required', 'boolean'],
            'due_at' => ['nullable', 'date_format:Y-m-d'],
            'targets' => ['required', 'array', 'min:1', 'max:100'],
            'targets.*.target_type' => ['required', Rule::in(['organizational_unit', 'role', 'user'])],
            'targets.*.target_id' => ['required', 'integer', 'min:1'],
        ]);

        $distribution = $distributions->issue($documentVersion, $request->user(), $data);

        return response()->json(['data' => [
            'id' => $distribution->id,
            'document_version_id' => $distribution->document_version_id,
            'issued_at' => $distribution->issued_at?->toISOString(),
            'issued_by' => $distribution->issuedBy?->name,
            'targets' => $distribution->targets->pluck('target_label_snapshot')->values(),
            'recipient_count' => $distribution->recipients->count(),
        ]], 201);
    }

    public function acknowledge(Request $request, DistributionRecipient $distributionRecipient, DocumentDistributionService $distributions): JsonResponse
    {
        $recipient = $distributions->acknowledge($distributionRecipient, $request->user());

        return response()->json(['data' => [
            'id' => $recipient->id,
            'acknowledgment_status' => $recipient->acknowledgment_status,
            'acknowledged_at' => $recipient->acknowledged_at?->toISOString(),
        ]]);
    }

    public function exempt(Request $request, DistributionRecipient $distributionRecipient, DocumentDistributionService $distributions): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:1', 'max:4000']]);
        $recipient = $distributions->exempt($distributionRecipient, $request->user(), $data['reason']);

        return response()->json(['data' => [
            'id' => $recipient->id,
            'acknowledgment_status' => $recipient->acknowledgment_status,
            'exempted_at' => $recipient->exempted_at?->toISOString(),
            'exemption_reason' => $recipient->exemption_reason,
        ]]);
    }
}
