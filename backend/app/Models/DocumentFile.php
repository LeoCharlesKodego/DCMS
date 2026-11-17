<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentFile extends Model
{
    protected $fillable = [
        'document_version_id', 'original_filename', 'storage_key', 'media_type', 'byte_size',
        'sha256', 'uploaded_by_user_id', 'uploaded_at', 'is_current',
    ];

    protected function casts(): array
    {
        return ['uploaded_at' => 'datetime', 'is_current' => 'boolean'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
