<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowStageInstance extends Model
{
    protected $fillable = [
        'workflow_instance_id', 'workflow_template_stage_id', 'pass_no',
        'status', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function workflowInstance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class);
    }

    public function templateStage(): BelongsTo
    {
        return $this->belongsTo(WorkflowTemplateStage::class, 'workflow_template_stage_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(WorkflowTask::class);
    }
}
