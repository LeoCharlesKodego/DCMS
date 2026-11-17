<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class WorkflowDecision extends Model
{
    public $timestamps = false;

    protected $fillable = ['workflow_task_id', 'actor_user_id', 'decision_type', 'comment', 'decided_at'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Workflow decisions are append-only.'));
        static::deleting(fn () => throw new LogicException('Workflow decisions are append-only.'));
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(WorkflowTask::class, 'workflow_task_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
