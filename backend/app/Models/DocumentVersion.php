<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DocumentVersion extends Model
{
    protected $fillable = ['document_id', 'sequence_no', 'revision_label', 'proposed_effective_date', 'effective_date', 'version_code', 'change_summary', 'status', 'created_by_user_id'];

    protected function casts(): array
    {
        return ['proposed_effective_date' => 'date:Y-m-d', 'effective_date' => 'date:Y-m-d'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(DocumentFile::class)->orderBy('uploaded_at');
    }

    public function workflowInstance(): HasOne
    {
        return $this->hasOne(WorkflowInstance::class);
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(DocumentDistribution::class)->orderByDesc('issued_at');
    }

}
