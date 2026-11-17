<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentMigrationBatch;
use App\Models\DocumentMigrationItem;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class DocumentMigrationService
{
    public function __construct(private readonly DocumentAuditService $audit, private readonly AuthorizationService $authorization) {}

    public function createBatch(User $actor, string $name, ?string $source): DocumentMigrationBatch
    {
        $batch = DocumentMigrationBatch::query()->create([
            'name' => trim($name), 'source_description' => $source,
            'status' => 'open', 'created_by_user_id' => $actor->id,
        ]);
        $this->audit->record('document_migration_batch', (int) $batch->id, null, $actor, 'migration.batch_created', null, 'open');

        return $batch;
    }

    /** @param list<UploadedFile> $files */
    public function addFiles(DocumentMigrationBatch $batch, User $actor, array $files): array
    {
        abort_unless($batch->status === 'open', 409, 'Closed migration batches cannot accept more source files.');
        $items = [];
        foreach ($files as $file) {
            $hash = hash_file('sha256', $file->getRealPath());
            if ($hash === false) {
                throw new RuntimeException('Could not calculate the source file integrity hash.');
            }
            $extension = strtolower($file->getClientOriginalExtension());
            $extension = $extension === 'jpeg' ? 'jpg' : $extension;
            $name = bin2hex(random_bytes(16)).'.'.$extension;
            $directory = 'document-migrations/'.$batch->id;
            $key = $directory.'/'.$name;
            $stored = Storage::disk('local')->putFileAs($directory, $file, $name);
            if ($stored === false) {
                throw new RuntimeException('A migration source file could not be stored on the private disk.');
            }
            try {
                $storedHash = hash_file('sha256', Storage::disk('local')->path($key));
                if ($storedHash === false || ! hash_equals($hash, $storedHash)) {
                    throw new RuntimeException('The stored migration source failed its SHA-256 integrity check.');
                }
                $metadata = [];
                foreach (['original_code', 'original_title', 'document_type', 'category', 'revision_label', 'approval_authority', 'approval_date', 'effective_date', 'owner', 'department', 'source_location', 'historical_version_of'] as $field) {
                    $metadata[$field] = null;
                }
                $item = $batch->items()->create([
                    'original_filename' => $this->safeFilename($file->getClientOriginalName()),
                    'storage_key' => $key,
                    'media_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'byte_size' => $file->getSize(), 'sha256' => $storedHash,
                    'source_metadata' => $metadata, 'source_location' => null,
                    'verification_status' => 'pending_verification', 'uploaded_by_user_id' => $actor->id,
                ]);
                $item->update(['duplicate_candidates' => $this->findDuplicates($item)]);
                if ($item->duplicate_candidates !== []) {
                    $item->update(['verification_status' => 'duplicate_candidate']);
                }
                $this->audit->record('document_migration_item', (int) $item->id, null, $actor, 'migration.source_uploaded', null, $item->verification_status, null, [
                    'batch_id' => (int) $batch->id, 'sha256' => $item->sha256, 'byte_size' => (int) $item->byte_size,
                ]);
                $items[] = $item->fresh();
            } catch (Throwable $exception) {
                Storage::disk('local')->delete($key);
                throw $exception;
            }
        }

        return $items;
    }

    public function updateMapping(DocumentMigrationItem $item, User $actor, array $source, array $mapped, ?string $sourceLocation): DocumentMigrationItem
    {
        abort_unless($item->batch()->value('status') === 'open', 409, 'Closed migration batches cannot be changed.');
        $before = $item->source_metadata;
        $item->update([
            'source_metadata' => $source, 'mapped_metadata' => $mapped, 'source_location' => $sourceLocation,
            'verification_status' => 'pending_verification', 'verified_by_user_id' => null, 'verified_at' => null,
        ]);
        $candidates = $this->findDuplicates($item->fresh());
        $item->update(['duplicate_candidates' => $candidates, 'verification_status' => $candidates === [] ? 'pending_verification' : 'duplicate_candidate']);
        $this->audit->record('document_migration_item', (int) $item->id, null, $actor, 'migration.metadata_mapped', null, null, null, [
            'source_metadata_before' => json_encode($before), 'source_metadata_after' => json_encode($source),
        ]);

        return $item->fresh();
    }

    public function verify(DocumentMigrationItem $item, User $actor, string $status, ?string $notes): DocumentMigrationItem
    {
        abort_unless($item->batch()->value('status') === 'open', 409, 'Closed migration batches cannot be verified.');
        if ($status === 'verified' && ! empty($item->duplicate_candidates) && trim((string) $notes) === '') {
            throw ValidationException::withMessages(['verification_notes' => 'Explain how the duplicate candidate was resolved before marking this item verified.']);
        }
        if (in_array($status, ['requires_correction', 'conflicting_information', 'rejected_for_migration'], true) && trim((string) $notes) === '') {
            throw ValidationException::withMessages(['verification_notes' => 'A reason is required for this verification status.']);
        }
        $from = $item->verification_status;
        $item->update(['verification_status' => $status, 'verification_notes' => $notes, 'verified_by_user_id' => $actor->id, 'verified_at' => now()]);
        $this->audit->record('document_migration_item', (int) $item->id, null, $actor, 'migration.item_verified', $from, $status, $notes, ['batch_id' => (int) $item->document_migration_batch_id]);

        return $item->fresh();
    }

    public function reconciliation(DocumentMigrationBatch $batch): array
    {
        $counts = $batch->items()->selectRaw('verification_status, count(*) as count')->groupBy('verification_status')->pluck('count', 'verification_status');
        $total = (int) $batch->items()->count();

        return [
            'batch_id' => (int) $batch->id, 'batch_name' => $batch->name, 'status' => $batch->status,
            'total' => $total, 'imported' => $total,
            'verified' => (int) ($counts['verified'] ?? 0),
            'pending' => (int) ($counts['pending_verification'] ?? 0),
            'rejected' => (int) ($counts['rejected_for_migration'] ?? 0),
            'duplicates' => (int) ($counts['duplicate_candidate'] ?? 0),
            'issues' => (int) (($counts['requires_correction'] ?? 0) + ($counts['conflicting_information'] ?? 0)),
            'obsolete' => (int) ($counts['obsolete'] ?? 0), 'superseded' => (int) ($counts['superseded'] ?? 0),
        ];
    }

    /** @param array{title: string, document_type_id: int, owning_unit_id: int, owner_user_id: int, revision_label: string} $mapping */
    public function registerVerified(DocumentMigrationItem $item, User $actor, array $mapping): Document
    {
        $targetKey = null;
        try {
            return DB::transaction(function () use ($item, $actor, $mapping, &$targetKey): Document {
                $locked = DocumentMigrationItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
                abort_unless($locked->verification_status === 'verified' && $locked->registered_document_id === null, 409, 'Only an unregistered verified source can be registered.');
                abort_unless($this->authorization->allows($actor, 'document.register'), 403, 'Only an active KMT registration assignment may register a verified migration item.');
                DocumentType::query()->whereKey($mapping['document_type_id'])->where('is_active', true)->firstOrFail();
                OrganizationalUnit::query()->whereKey($mapping['owning_unit_id'])->where('is_active', true)->firstOrFail();
                User::query()->whereKey($mapping['owner_user_id'])->where('is_active', true)->firstOrFail();

                $document = Document::query()->create([
                    'document_type_id' => $mapping['document_type_id'],
                    'owner_user_id' => $mapping['owner_user_id'],
                    'owning_unit_id' => $mapping['owning_unit_id'],
                    'created_by_user_id' => $actor->id,
                    'title' => trim($mapping['title']),
                    'current_revision_label' => trim($mapping['revision_label']),
                    'status' => 'pending_code',
                ]);
                $version = $document->versions()->create([
                    'sequence_no' => 1,
                    'revision_label' => trim($mapping['revision_label']),
                    'status' => 'draft',
                    'created_by_user_id' => $actor->id,
                    'change_summary' => 'Imported source file; approval and release were not inferred.',
                ]);
                $extension = strtolower(pathinfo($locked->original_filename, PATHINFO_EXTENSION));
                $extension = $extension === 'jpeg' ? 'jpg' : $extension;
                $filename = bin2hex(random_bytes(16)).'.'.$extension;
                $directory = 'documents/'.$document->id.'/versions/'.$version->id;
                $targetKey = $directory.'/'.$filename;
                if (! Storage::disk('local')->copy($locked->storage_key, $targetKey)) {
                    throw new RuntimeException('The verified source could not be copied into the private document store.');
                }
                $copyHash = hash_file('sha256', Storage::disk('local')->path($targetKey));
                if ($copyHash === false || ! hash_equals($locked->sha256, $copyHash)) {
                    throw new RuntimeException('The registered source copy failed its SHA-256 integrity check.');
                }
                $file = $version->files()->create([
                    'original_filename' => $locked->original_filename,
                    'storage_key' => $targetKey,
                    'media_type' => $locked->media_type,
                    'byte_size' => $locked->byte_size,
                    'sha256' => $copyHash,
                    'uploaded_by_user_id' => $actor->id,
                    'uploaded_at' => now(),
                ]);
                $this->audit->record('document', (int) $document->id, $document, $actor, 'document.migration_registered', null, 'pending_code', null, [
                    'migration_item_id' => (int) $locked->id, 'source_revision_label' => trim($mapping['revision_label']),
                ]);
                $this->audit->record('document_file', (int) $file->id, $document, $actor, 'file.migration_imported', null, 'draft', null, [
                    'migration_item_id' => (int) $locked->id, 'sha256' => $copyHash,
                ]);
                $locked->update([
                    'registered_document_id' => $document->id,
                    'mapped_metadata' => [
                        'title' => trim($mapping['title']), 'document_type_id' => (int) $mapping['document_type_id'],
                        'owning_unit_id' => (int) $mapping['owning_unit_id'], 'owner_user_id' => (int) $mapping['owner_user_id'],
                        'revision_label' => trim($mapping['revision_label']),
                    ],
                ]);
                $this->audit->record('document_migration_item', (int) $locked->id, $document, $actor, 'migration.item_registered', 'verified', 'registered', null, [
                    'document_id' => (int) $document->id,
                ]);
                return $document->load('versions.files');
            });
        } catch (Throwable $exception) {
            if ($targetKey !== null) Storage::disk('local')->delete($targetKey);
            throw $exception;
        }
    }

    private function findDuplicates(DocumentMigrationItem $item): array
    {
        $matches = [];
        $sameHash = DocumentMigrationItem::query()->where('id', '<>', $item->id)->where('sha256', $item->sha256)->limit(10)->get(['id', 'original_filename', 'verification_status']);
        foreach ($sameHash as $candidate) {
            $matches[] = ['type' => 'file_hash', 'migration_item_id' => (int) $candidate->id, 'label' => $candidate->original_filename, 'status' => $candidate->verification_status];
        }
        $source = $item->source_metadata ?? [];
        $sourceTitle = trim((string) ($source['original_title'] ?? ''));
        $sameTitle = $sourceTitle === '' ? collect() : Document::query()->whereRaw('LOWER(title) = ?', [mb_strtolower($sourceTitle)])->limit(10)->get(['id', 'title']);
        foreach ($sameTitle as $document) {
            $matches[] = ['type' => 'title', 'document_id' => (int) $document->id, 'label' => $document->title];
        }
        $sameFile = DocumentFile::query()->where('sha256', $item->sha256)->with('version:id,document_id')->limit(10)->get(['id', 'document_version_id', 'original_filename']);
        foreach ($sameFile as $file) {
            $matches[] = ['type' => 'file_hash', 'document_id' => (int) $file->version->document_id, 'label' => $file->original_filename];
        }
        $sourceCode = trim((string) ($source['original_code'] ?? ''));
        if ($sourceCode !== '') {
            $sameCode = DB::table('master_code_register_entries')->whereRaw('LOWER(base_code) = ?', [mb_strtolower($sourceCode)])->limit(10)->get(['id', 'base_code', 'document_id']);
            foreach ($sameCode as $entry) {
                $matches[] = ['type' => 'code', 'document_id' => (int) $entry->document_id, 'label' => $entry->base_code];
            }
        }

        return $matches;
    }

    private function safeFilename(string $filename): string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        $filename = preg_replace('/[\x00-\x1F\x7F]/u', '', $filename) ?? '';

        return $filename === '' ? 'source-document' : mb_substr($filename, 0, 255);
    }
}
