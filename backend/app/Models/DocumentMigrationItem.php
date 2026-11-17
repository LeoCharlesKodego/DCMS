<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentMigrationItem extends Model
{
    protected $fillable = [
        'document_migration_batch_id', 'original_filename', 'storage_key', 'media_type', 'byte_size', 'sha256',
        'source_location', 'source_metadata', 'mapped_metadata', 'duplicate_candidates', 'verification_status',
        'verification_notes', 'verified_by_user_id', 'verified_at', 'registered_document_id', 'uploaded_by_user_id',
    ];

    protected function casts(): array
    {
        return ['source_metadata' => 'array', 'mapped_metadata' => 'array', 'duplicate_candidates' => 'array', 'verified_at' => 'datetime'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(DocumentMigrationBatch::class, 'document_migration_batch_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function registeredDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'registered_document_id');
    }
}
