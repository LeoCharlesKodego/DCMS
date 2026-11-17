<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WorkflowTemplateStage extends Model
{
    protected $fillable = [
        'workflow_template_version_id', 'stage_key', 'name', 'purpose', 'stage_order',
        'parallel_group', 'action_type', 'is_mandatory', 'required_decisions',
    ];

    protected function casts(): array
    {
        return ['is_mandatory' => 'boolean'];
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowTemplateVersion::class, 'workflow_template_version_id');
    }

    public function eligibleRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'workflow_stage_eligible_roles')
            ->withPivot('scope_type');
    }
}
