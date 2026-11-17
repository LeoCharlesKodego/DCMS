<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowInstance extends Model
{
    protected $fillable = [
        'document_version_id', 'workflow_template_version_id', 'status',
        'started_by_user_id', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class);
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowTemplateVersion::class, 'workflow_template_version_id');
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by_user_id');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(WorkflowStageInstance::class)->orderBy('id');
    }
}
