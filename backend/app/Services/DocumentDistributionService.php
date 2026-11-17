<?php

namespace App\Services;

use App\Models\DocumentDistribution;
use App\Models\DocumentVersion;
use App\Models\DistributionRecipient;
use App\Models\DistributionTarget;
use App\Models\Institution;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentDistributionService
{
    public function __construct(
        private readonly AuthorizationService $authorization,
        private readonly DocumentAuditService $audit,
    ) {}

    /** @param array{targets: list<array{target_type: string, target_id: int}>, acknowledgment_required: bool, due_at?: string|null} $data */
    public function issue(DocumentVersion $version, User $issuer, array $data): DocumentDistribution
    {
        return DB::transaction(function () use ($version, $issuer, $data): DocumentDistribution {
            $lockedVersion = DocumentVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedVersion->status === 'released', 409, 'Only an exact released version can be distributed.');
            $document = $lockedVersion->document()->with('owningUnit')->lockForUpdate()->firstOrFail();
            abort_unless($this->authorization->allows($issuer, 'document.distribute'), 403, 'Only an active KMT distribution assignment may issue a document.');
            $targets = $data['targets'];
            $keys = array_map(fn (array $target): string => $target['target_type'].':'.$target['target_id'], $targets);
            if (count($keys) !== count(array_unique($keys))) {
                throw ValidationException::withMessages(['targets' => 'A distribution target may only be selected once.']);
            }

            $distribution = DocumentDistribution::query()->create([
                'document_version_id' => $lockedVersion->id,
                'issued_by_user_id' => $issuer->id,
                'issued_at' => now(),
            ]);
            $recipientSources = [];
            foreach ($targets as $targetData) {
                [$label, $recipientIds] = $this->resolveTarget($targetData['target_type'], (int) $targetData['target_id']);
                if ($recipientIds === []) {
                    throw ValidationException::withMessages(['targets' => "The selected {$targetData['target_type']} target has no active users assigned at the time of issue."]);
                }

                $target = DistributionTarget::query()->create([
                    'document_distribution_id' => $distribution->id,
                    'target_type' => $targetData['target_type'],
                    'organizational_unit_id' => $targetData['target_type'] === 'organizational_unit' ? $targetData['target_id'] : null,
                    'role_id' => $targetData['target_type'] === 'role' ? $targetData['target_id'] : null,
                    'user_id' => $targetData['target_type'] === 'user' ? $targetData['target_id'] : null,
                    'target_label_snapshot' => $label,
                    'created_at' => now(),
                ]);

                foreach ($recipientIds as $recipientId) {
                    $recipientSources[$recipientId][] = $target->id;
                }
            }

            foreach ($recipientSources as $recipientId => $sourceTargetIds) {
                $recipientUser = User::query()->whereKey($recipientId)->where('is_active', true)->firstOrFail();
                $recipient = DistributionRecipient::query()->create([
                    'document_distribution_id' => $distribution->id,
                    'user_id' => $recipientUser->id,
                    'recipient_name_at_issue' => $recipientUser->name,
                    'acknowledgment_required' => $data['acknowledgment_required'],
                    'due_at' => $data['acknowledgment_required'] ? ($data['due_at'] ?? null) : null,
                    'acknowledgment_status' => $data['acknowledgment_required'] ? 'pending' : 'not_required',
                ]);
                UserNotification::query()->create([
                    'user_id' => $recipientUser->id,
                    'event_type' => 'distribution.received',
                    'title' => 'Controlled document distributed',
                    'body' => $lockedVersion->document->title.' · revision '.$lockedVersion->revision_label,
                    'target_type' => 'distribution_recipient',
                    'target_id' => $recipient->id,
                ]);
                foreach ($sourceTargetIds as $targetId) {
                    $recipient->sources()->create([
                        'distribution_target_id' => $targetId,
                        'created_at' => now(),
                    ]);
                }
            }

            $this->audit->record('document_distribution', $distribution->id, $document, $issuer, 'distribution.issued', null, 'issued', null, [
                'document_version_id' => (int) $lockedVersion->id,
                'revision_label' => $lockedVersion->revision_label,
                'target_count' => count($targets),
                'recipient_count' => count($recipientSources),
                'acknowledgment_required' => $data['acknowledgment_required'],
                'due_at' => $data['acknowledgment_required'] ? ($data['due_at'] ?? null) : null,
            ]);

            return $distribution->load(['issuedBy:id,name', 'targets', 'recipients.user:id,name', 'recipients.sources']);
        });
    }

    public function acknowledge(DistributionRecipient $recipient, User $actor): DistributionRecipient
    {
        return DB::transaction(function () use ($recipient, $actor): DistributionRecipient {
            $lockedRecipient = DistributionRecipient::query()->whereKey($recipient->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $lockedRecipient->user_id === (int) $actor->id, 403, 'Only the named recipient may acknowledge this distribution.');
            abort_unless($actor->is_active, 403, 'An active account is required to acknowledge receipt.');
            abort_unless($lockedRecipient->acknowledgment_required && $lockedRecipient->acknowledgment_status === 'pending', 409, 'This recipient does not have a pending acknowledgment.');

            $lockedRecipient->update([
                'acknowledgment_status' => 'acknowledged',
                'acknowledged_by_user_id' => $actor->id,
                'acknowledged_at' => now(),
            ]);
            $distribution = $lockedRecipient->distribution()->with('documentVersion.document')->firstOrFail();
            $this->audit->record('distribution_recipient', $lockedRecipient->id, $distribution->documentVersion->document, $actor, 'distribution.acknowledged', 'pending', 'acknowledged', null, [
                'document_distribution_id' => (int) $distribution->id,
                'document_version_id' => (int) $distribution->document_version_id,
            ]);

            return $lockedRecipient->refresh();
        });
    }

    public function exempt(DistributionRecipient $recipient, User $actor, string $reason): DistributionRecipient
    {
        return DB::transaction(function () use ($recipient, $actor, $reason): DistributionRecipient {
            $lockedRecipient = DistributionRecipient::query()->whereKey($recipient->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->is_active && $this->authorization->allows($actor, 'document.distribute'), 403, 'Only an active KMT distribution assignment may exempt acknowledgment.');
            abort_unless(trim($reason) !== '', 422, 'An exemption reason is required.');
            abort_unless($lockedRecipient->acknowledgment_required && $lockedRecipient->acknowledgment_status === 'pending', 409, 'Only one pending recipient acknowledgment may be exempted.');

            $lockedRecipient->update([
                'acknowledgment_status' => 'exempted',
                'exempted_by_user_id' => $actor->id,
                'exempted_at' => now(),
                'exemption_reason' => trim($reason),
            ]);
            $distribution = $lockedRecipient->distribution()->with('documentVersion.document')->firstOrFail();
            $this->audit->record('distribution_recipient', $lockedRecipient->id, $distribution->documentVersion->document, $actor, 'distribution.exempted', 'pending', 'exempted', trim($reason), [
                'document_distribution_id' => (int) $distribution->id,
                'document_version_id' => (int) $distribution->document_version_id,
            ]);

            return $lockedRecipient->refresh();
        });
    }

    public function canAccessVersion(User $user, int $documentVersionId): bool
    {
        return DistributionRecipient::query()
            ->where('user_id', $user->id)
            ->whereHas('distribution', fn ($query) => $query->where('document_version_id', $documentVersionId))
            ->exists();
    }

    /** @return list<array<string, mixed>> */
    public function inboxFor(User $user): array
    {
        $today = Institution::localDate();

        return DistributionRecipient::query()
            ->with([
                'distribution.issuedBy:id,name',
                'distribution.documentVersion.document:id,title,current_revision_label',
                'distribution.documentVersion.files:id,document_version_id,original_filename,byte_size,sha256',
            ])
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(function (DistributionRecipient $recipient) use ($today): array {
                $distribution = $recipient->distribution;
                $version = $distribution->documentVersion;
                $status = $recipient->acknowledgment_status;
                if ($status === 'pending' && $recipient->acknowledgment_required && $recipient->due_at !== null
                    && $recipient->due_at->format('Y-m-d') < $today) {
                    $status = 'overdue';
                }

                return [
                    'recipient_id' => (int) $recipient->id,
                    'distribution_id' => (int) $distribution->id,
                    'document_title' => $version->document->title,
                    'revision_label' => $version->revision_label,
                    'document_version_id' => (int) $version->id,
                    'issued_at' => $distribution->issued_at?->toISOString(),
                    'issued_by' => $distribution->issuedBy?->name,
                    'acknowledgment_required' => $recipient->acknowledgment_required,
                    'status' => $status,
                    'due_at' => $recipient->due_at?->format('Y-m-d'),
                    'acknowledged_at' => $recipient->acknowledged_at?->toISOString(),
                    'can_acknowledge' => $recipient->acknowledgment_required && $recipient->acknowledgment_status === 'pending',
                    'files' => $version->files->map(fn ($file): array => [
                        'id' => (int) $file->id,
                        'original_filename' => $file->original_filename,
                        'byte_size' => (int) $file->byte_size,
                        'sha256' => $file->sha256,
                    ])->values(),
                ];
            })
            ->values()
            ->all();
    }

    /** @return list<array{target_type: string, id: int, label: string}> */
    public function targetOptions(): array
    {
        $units = OrganizationalUnit::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
            ->map(fn (OrganizationalUnit $unit): array => ['target_type' => 'organizational_unit', 'id' => (int) $unit->id, 'label' => $unit->name]);
        $roles = Role::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name'])
            ->map(fn (Role $role): array => ['target_type' => 'role', 'id' => (int) $role->id, 'label' => $role->code ? $role->code.' — '.$role->name : $role->name]);
        $users = User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email'])
            ->map(fn (User $user): array => ['target_type' => 'user', 'id' => (int) $user->id, 'label' => $user->name.' — '.$user->email]);

        return $units->concat($roles)->concat($users)->values()->all();
    }

    /** @return list<array<string, mixed>> */
    public function visibleFor(DocumentVersion $version, User $viewer): array
    {
        $version->loadMissing('document');
        $canManage = $this->authorization->allows($viewer, 'document.distribute');
        $today = Institution::localDate();

        return DocumentDistribution::query()
            ->with(['issuedBy:id,name', 'targets', 'recipients.user:id,name', 'recipients.acknowledgedBy:id,name'])
            ->where('document_version_id', $version->id)
            ->orderByDesc('issued_at')
            ->get()
            ->map(function (DocumentDistribution $distribution) use ($viewer, $canManage, $today): array {
                $recipients = $distribution->recipients
                    ->filter(fn (DistributionRecipient $recipient): bool => $canManage || (int) $recipient->user_id === (int) $viewer->id)
                    ->map(function (DistributionRecipient $recipient) use ($today): array {
                        $status = $recipient->acknowledgment_status;
                        if ($status === 'pending' && $recipient->acknowledgment_required && $recipient->due_at !== null
                            && $recipient->due_at?->format('Y-m-d') < $today) {
                            $status = 'overdue';
                        }

                        return [
                            'id' => (int) $recipient->id,
                            'user_id' => (int) $recipient->user_id,
                            'name' => $recipient->recipient_name_at_issue,
                            'acknowledgment_required' => $recipient->acknowledgment_required,
                            'status' => $status,
                            'due_at' => $recipient->due_at?->format('Y-m-d'),
                            'acknowledged_at' => $recipient->acknowledged_at?->toISOString(),
                            'acknowledged_by' => $recipient->acknowledgedBy?->name,
                            'can_acknowledge' => $recipient->acknowledgment_required
                                && $recipient->acknowledgment_status === 'pending'
                                && (int) $recipient->user_id === (int) $viewer->id,
                            'can_exempt' => $canManage && $recipient->acknowledgment_required
                                && $recipient->acknowledgment_status === 'pending',
                        ];
                    })->values();

                return [
                    'id' => (int) $distribution->id,
                    'issued_at' => $distribution->issued_at?->toISOString(),
                    'issued_by' => $distribution->issuedBy?->name,
                    'targets' => $canManage ? $distribution->targets->pluck('target_label_snapshot')->values() : [],
                    'recipients' => $recipients,
                ];
            })->values()->all();
    }

    /** @return array{string, list<int>} */
    private function resolveTarget(string $type, int $targetId): array
    {
        $today = Institution::localDate();
        if ($type === 'organizational_unit') {
            $unit = OrganizationalUnit::query()->whereKey($targetId)->where('is_active', true)->first();
            if (! $unit) {
                throw ValidationException::withMessages(['targets' => 'Choose an active organizational unit.']);
            }
            $recipients = DB::table('user_role_assignments as assignments')
                ->join('users', 'users.id', '=', 'assignments.user_id')
                ->join('roles', 'roles.id', '=', 'assignments.role_id')
                ->where('assignments.organizational_unit_id', $unit->id)
                ->where('users.is_active', true)
                ->where('roles.is_active', true)
                ->whereDate('assignments.valid_from', '<=', $today)
                ->where(fn ($query) => $query->whereNull('assignments.valid_until')->orWhereDate('assignments.valid_until', '>=', $today))
                ->distinct()->pluck('users.id')->map(fn ($id): int => (int) $id)->all();

            return ['Unit: '.$unit->name, $recipients];
        }

        if ($type === 'role') {
            $role = Role::query()->whereKey($targetId)->where('is_active', true)->first();
            if (! $role) {
                throw ValidationException::withMessages(['targets' => 'Choose an active role.']);
            }
            $recipients = DB::table('user_role_assignments as assignments')
                ->join('users', 'users.id', '=', 'assignments.user_id')
                ->where('assignments.role_id', $role->id)
                ->where('users.is_active', true)
                ->whereDate('assignments.valid_from', '<=', $today)
                ->where(fn ($query) => $query->whereNull('assignments.valid_until')->orWhereDate('assignments.valid_until', '>=', $today))
                ->distinct()->pluck('users.id')->map(fn ($id): int => (int) $id)->all();

            return ['Role: '.($role->code ? $role->code.' — ' : '').$role->name, $recipients];
        }

        if ($type === 'user') {
            $user = User::query()->whereKey($targetId)->where('is_active', true)->first();
            if (! $user) {
                throw ValidationException::withMessages(['targets' => 'Choose an active user.']);
            }

            return ['User: '.$user->name, [(int) $user->id]];
        }

        throw ValidationException::withMessages(['targets' => 'Choose an organizational unit, role, or individual user target.']);
    }
}
