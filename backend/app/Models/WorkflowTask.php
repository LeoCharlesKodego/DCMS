<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowTask extends Model
{
    protected $fillable = [
        'workflow_stage_instance_id', 'role_id', 'organizational_unit_id', 'assigned_user_id',
        'status', 'assigned_at', 'due_at',
    ];

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime', 'due_at' => 'datetime'];
    }

    public function stageInstance(): BelongsTo
    {
        return $this->belongsTo(WorkflowStageInstance::class, 'workflow_stage_instance_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function organizationalUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
