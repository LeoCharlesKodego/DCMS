<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Document;
use App\Models\User;

class DocumentAuditService
{
    /** @param array<string, scalar|null> $metadata */
    public function record(
        string $targetType,
        int $targetId,
        ?Document $document,
        ?User $actor,
        string $eventType,
        ?string $fromState = null,
        ?string $toState = null,
        ?string $reason = null,
        array $metadata = [],
    ): AuditEvent {
        return AuditEvent::query()->create([
            'target_type' => $targetType,
            'target_id' => $targetId,
            'document_id' => $document?->id,
            'actor_user_id' => $actor?->id,
            'event_type' => $eventType,
            'from_state' => $fromState,
            'to_state' => $toState,
            'occurred_at' => now(),
            'reason' => $reason,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }
}
