<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class DocumentVersionStorageService
{
    public function __construct(private readonly SubmissionValidationService $validation) {}
    /** @param list<UploadedFile> $files
     *  @param array{revision_kind?: string, change_summary?: string|null} $metadata
     */
    public function createVersion(Document $document, User $actor, array $metadata, array $files, DocumentAuditService $audit): DocumentVersion
    {
        $storedKeys = [];

        try {
            return DB::transaction(function () use ($document, $actor, $metadata, $files, $audit, &$storedKeys): DocumentVersion {
                $lockedDocument = Document::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
                $previous = DocumentVersion::query()
                    ->where('document_id', $lockedDocument->id)
                    ->orderByDesc('sequence_no')
                    ->lockForUpdate()
                    ->first();
                if ($previous && ! in_array($previous->status, ['released', 'returned_for_revision'], true)) {
                    throw ValidationException::withMessages(['version' => 'Complete or return the current version before starting another revision.']);
                }
                $sequenceNo = $previous ? $previous->sequence_no + 1 : 1;

                if (! $previous) {
                    $revisionLabel = '1.0';
                    if (! empty($metadata['revision_kind'])) {
                        throw ValidationException::withMessages(['revision_kind' => 'The first version starts at 1.0.']);
                    }
                } else {
                    if (empty($metadata['revision_kind'])) {
                        throw ValidationException::withMessages(['revision_kind' => 'Choose whether this is a minor or major revision.']);
                    }
                    if (trim((string) ($metadata['change_summary'] ?? '')) === '') {
                        throw ValidationException::withMessages(['change_summary' => 'Enter a change summary for a revision.']);
                    }
                    if (! preg_match('/^(\d+)\.(\d+)$/', $previous->revision_label, $parts)) {
                        throw ValidationException::withMessages(['revision_kind' => 'The previous revision label is not a valid Major.Minor value.']);
                    }

                    $major = (int) $parts[1];
                    $minor = (int) $parts[2];
                    $revisionLabel = $metadata['revision_kind'] === 'major'
                        ? ($major + 1).'.0'
                        : $major.'.'.($minor + 1);
                }

                $version = $lockedDocument->versions()->create([
                    'sequence_no' => $sequenceNo,
                    'revision_label' => $revisionLabel,
                    'change_summary' => $metadata['change_summary'] ?? null,
                    'status' => 'draft',
                    'created_by_user_id' => $actor->id,
                ]);

                foreach ($files as $upload) {
                    $extension = strtolower($upload->getClientOriginalExtension());
                    $extension = $extension === 'jpeg' ? 'jpg' : $extension;
                    $filename = bin2hex(random_bytes(16)).'.'.$extension;
                    $directory = 'documents/'.$lockedDocument->id.'/versions/'.$version->id;
                    $storageKey = $directory.'/'.$filename;
                    $sha256 = hash_file('sha256', $upload->getRealPath());
                    if ($sha256 === false) {
                        throw new RuntimeException('The SHA-256 integrity hash could not be calculated.');
                    }

                    $stored = Storage::disk('local')->putFileAs($directory, $upload, $filename);
                    if ($stored === false) {
                        throw new RuntimeException('A document file could not be stored on the private disk.');
                    }
                    $storedKeys[] = $storageKey;

                    $storedSha256 = hash_file('sha256', Storage::disk('local')->path($storageKey));
                    if ($storedSha256 === false || ! hash_equals($sha256, $storedSha256)) {
                        throw new RuntimeException('The stored file failed its SHA-256 integrity check.');
                    }

                    $fileRecord = $version->files()->create([
                        'original_filename' => $this->safeOriginalFilename($upload->getClientOriginalName()),
                        'storage_key' => $storageKey,
                        'media_type' => $upload->getMimeType() ?: 'application/octet-stream',
                        'byte_size' => $upload->getSize(),
                        'sha256' => $storedSha256,
                        'uploaded_by_user_id' => $actor->id,
                        'uploaded_at' => now(),
                    ]);
                    $this->validation->validate($fileRecord, $actor);
                    $audit->record('document_file', $fileRecord->id, $lockedDocument, $actor, 'file.uploaded', null, null, null, [
                        'media_type' => $fileRecord->media_type,
                        'byte_size' => $fileRecord->byte_size,
                        'sha256' => $fileRecord->sha256,
                    ]);
                }

                $audit->record('document_version', $version->id, $lockedDocument, $actor, 'version.draft_created', null, 'draft', $metadata['change_summary'] ?? null, [
                    'revision_label' => $revisionLabel,
                    'files_count' => count($files),
                ]);

                $lockedDocument->update(['current_revision_label' => $revisionLabel]);
                if ($previous?->status === 'released' && $lockedDocument->status !== 'under_revision') {
                    $previousDocumentState = $lockedDocument->status;
                    $lockedDocument->update(['status' => 'under_revision']);
                    $audit->record('document', $lockedDocument->id, $lockedDocument, $actor, 'document.revision_started', $previousDocumentState, 'under_revision');
                }

                return $version->load('files');
            });
        } catch (Throwable $exception) {
            foreach ($storedKeys as $storageKey) {
                Storage::disk('local')->delete($storageKey);
            }

            throw $exception;
        }
    }

    /** @param list<UploadedFile> $files */
    public function replaceCurrentFiles(DocumentVersion $version, User $actor, array $files, DocumentAuditService $audit): DocumentVersion
    {
        $storedKeys = [];
        try {
            return DB::transaction(function () use ($version, $actor, $files, $audit, &$storedKeys): DocumentVersion {
                $locked = DocumentVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();
                abort_unless(in_array($locked->status, ['draft', 'returned_for_revision'], true), 409, 'Only a draft or returned version may replace its current files.');
                $document = Document::query()->whereKey($locked->document_id)->lockForUpdate()->firstOrFail();
                $newFiles = [];
                foreach ($files as $upload) {
                    $extension = strtolower($upload->getClientOriginalExtension());
                    $extension = $extension === 'jpeg' ? 'jpg' : $extension;
                    $filename = bin2hex(random_bytes(16)).'.'.$extension;
                    $directory = 'documents/'.$document->id.'/versions/'.$locked->id;
                    $storageKey = $directory.'/'.$filename;
                    $sha256 = hash_file('sha256', $upload->getRealPath());
                    if ($sha256 === false) throw new RuntimeException('The SHA-256 integrity hash could not be calculated.');
                    if (Storage::disk('local')->putFileAs($directory, $upload, $filename) === false) throw new RuntimeException('A replacement file could not be stored.');
                    $storedKeys[] = $storageKey;
                    $storedHash = hash_file('sha256', Storage::disk('local')->path($storageKey));
                    if ($storedHash === false || ! hash_equals($sha256, $storedHash)) throw new RuntimeException('The replacement file failed its SHA-256 integrity check.');
                    $newFiles[] = $locked->files()->create([
                        'original_filename' => $this->safeOriginalFilename($upload->getClientOriginalName()),
                        'storage_key' => $storageKey, 'media_type' => $upload->getMimeType() ?: 'application/octet-stream',
                        'byte_size' => $upload->getSize(), 'sha256' => $storedHash,
                        'uploaded_by_user_id' => $actor->id, 'uploaded_at' => now(), 'is_current' => true,
                    ]);
                }
                $locked->files()->where('is_current', true)->whereNotIn('id', collect($newFiles)->pluck('id'))->update(['is_current' => false]);
                foreach ($newFiles as $file) {
                    $this->validation->validate($file, $actor);
                    $audit->record('document_file', $file->id, $document, $actor, 'file.replaced', null, null, null, ['sha256' => $file->sha256, 'replaces_current_file_set' => true]);
                }
                return $locked->refresh()->load('files');
            });
        } catch (Throwable $exception) {
            foreach ($storedKeys as $key) Storage::disk('local')->delete($key);
            throw $exception;
        }
    }

    public function download(DocumentFile $file): mixed
    {
        abort_unless(Storage::disk('local')->exists($file->storage_key), 404, 'The stored document file is missing.');

        $storedSha256 = hash_file('sha256', Storage::disk('local')->path($file->storage_key));
        abort_unless($storedSha256 !== false && hash_equals($file->sha256, $storedSha256), 500, 'The stored document file failed its integrity check.');

        return Storage::disk('local')->download(
            $file->storage_key,
            $this->safeOriginalFilename($file->original_filename),
            ['Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff'],
        );
    }

    private function safeOriginalFilename(string $filename): string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        $filename = preg_replace('/[\x00-\x1F\x7F]/u', '', $filename) ?? '';

        return $filename !== '' ? mb_substr($filename, 0, 255) : 'document-file';
    }
}
