<?php

namespace App\Services;

use App\Models\DispositionRequest;
use App\Models\OperationalRecord;
use App\Models\RetentionSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class OperationalRecordsService
{
    public function __construct(private readonly AuthorizationService $authorization, private readonly DocumentAuditService $audit) {}

    public function createSchedule(User $actor, array $data): RetentionSchedule
    {
        abort_unless($this->authorization->allows($actor, 'records.manage'), 403);
        abort_if((bool) ($data['is_active'] ?? false), 409, 'Retention schedules cannot be activated while D-011 periods and legal bases remain unresolved.');
        return RetentionSchedule::query()->create($data + ['is_active' => false]);
    }

    public function createRecord(User $actor, array $data): OperationalRecord
    {
        abort_unless($this->authorization->allows($actor, 'records.manage'), 403);
        $record = OperationalRecord::query()->create($data + ['created_by_user_id' => $actor->id, 'status' => 'active']);
        $this->audit->record('operational_record', (int) $record->id, null, $actor, 'operational_record.created', null, 'active');
        return $record;
    }

    public function placeHold(OperationalRecord $record, User $actor, string $reason, ?string $reference): void
    {
        abort_unless($this->authorization->allows($actor, 'records.manage'), 403);
        abort_unless($record->status !== 'disposed', 409, 'A disposed record cannot accept a new hold.');
        $hold = $record->holds()->create(['reason' => $reason, 'authority_reference' => $reference, 'placed_by_user_id' => $actor->id, 'placed_at' => now()]);
        $record->update(['status' => 'on_hold']);
        $this->audit->record('operational_record', (int) $record->id, null, $actor, 'operational_record.hold_placed', null, 'on_hold', $reason, ['hold_id' => (int) $hold->id]);
    }

    public function releaseHold(OperationalRecord $record, User $actor, int $holdId, string $reason): void
    {
        abort_unless($this->authorization->allows($actor, 'records.manage'), 403);
        abort_unless($record->status !== 'disposed', 409, 'A disposed record cannot be reopened by releasing a hold.');
        $hold = $record->holds()->whereKey($holdId)->whereNull('released_at')->firstOrFail();
        $hold->update(['released_by_user_id' => $actor->id, 'released_at' => now(), 'release_reason' => $reason]);
        if (! $record->holds()->whereNull('released_at')->exists()) $record->update(['status' => 'active']);
        $this->audit->record('operational_record', (int) $record->id, null, $actor, 'operational_record.hold_released', 'on_hold', 'active', $reason, ['hold_id' => (int) $hold->id]);
    }

    public function requestDisposition(OperationalRecord $record, User $actor, string $reason): DispositionRequest
    {
        abort_unless($this->authorization->allows($actor, 'records.manage'), 403);
        abort_unless($record->status === 'active', 409, 'Only active records may enter disposition review.');
        abort_if($record->holds()->whereNull('released_at')->exists(), 409, 'A record hold blocks disposition.');
        abort_unless($this->retentionElapsed($record), 409, 'No approved active retention schedule applies or the configured retention period has not elapsed.');
        $request = $record->dispositionRequests()->create(['requested_by_user_id' => $actor->id, 'reason' => $reason, 'status' => 'pending']);
        $this->audit->record('operational_record', (int) $record->id, null, $actor, 'disposition.requested', null, 'pending', $reason, ['disposition_request_id' => (int) $request->id]);
        return $request;
    }

    public function decide(DispositionRequest $request, User $actor, string $decision, string $reason): DispositionRequest
    {
        abort_unless($this->authorization->allows($actor, 'records.manage'), 403);
        abort_unless($request->status === 'pending', 409, 'Only pending disposition requests may be decided.');
        if ($decision === 'approved') {
            $record = $request->record()->with(['holds', 'schedule'])->firstOrFail();
            abort_if($record->holds->contains(fn ($hold) => $hold->released_at === null), 409, 'A record hold blocks disposition approval.');
            abort_unless($this->retentionElapsed($record) && $record->schedule?->disposition_method, 409, 'An approved schedule, elapsed period, and disposition method are required before approval.');
        }
        $status = $decision === 'defer' ? 'deferred' : $decision;
        $request->update(['status' => $status, 'decided_by_user_id' => $actor->id, 'decided_at' => now(), 'decision_reason' => $reason]);
        $this->audit->record('operational_record', (int) $request->operational_record_id, null, $actor, 'disposition.'.$status, 'pending', $status, $reason, ['disposition_request_id' => (int) $request->id]);
        return $request->fresh();
    }

    public function execute(DispositionRequest $request, User $actor, string $evidence): DispositionRequest
    {
        abort_unless($this->authorization->allows($actor, 'records.manage'), 403);
        abort_unless($request->status === 'approved', 409, 'Only approved disposition requests may be executed.');
        $record = $request->record()->with('holds')->firstOrFail();
        abort_if($record->holds->contains(fn ($hold) => $hold->released_at === null), 409, 'A record hold blocks disposition execution.');
        abort_unless($this->retentionElapsed($record) && $record->schedule?->disposition_method, 409, 'An approved active schedule and disposition method are required at execution time.');
        $request->update(['executed_by_user_id' => $actor->id, 'executed_at' => now(), 'execution_evidence' => $evidence]);
        $record->update(['status' => 'disposed']);
        $this->audit->record('operational_record', (int) $record->id, null, $actor, 'disposition.executed', 'approved', 'disposed', $evidence, ['disposition_request_id' => (int) $request->id]);
        return $request->fresh();
    }

    private function retentionElapsed(OperationalRecord $record): bool
    {
        $schedule = $record->schedule;
        if (! $schedule || ! $schedule->is_active || $schedule->retention_duration === null || ! $schedule->retention_unit || $record->retention_triggered_on === null) return false;
        $trigger = CarbonImmutable::parse($record->retention_triggered_on);
        $eligible = match ($schedule->retention_unit) {
            'days' => $trigger->addDays($schedule->retention_duration),
            'months' => $trigger->addMonthsNoOverflow($schedule->retention_duration),
            'years' => $trigger->addYearsNoOverflow($schedule->retention_duration),
            default => null,
        };
        return $eligible !== null && $eligible->toDateString() <= \App\Models\Institution::localDate();
    }
}
