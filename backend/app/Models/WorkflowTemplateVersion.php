<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowTemplateVersion extends Model
{
    protected $fillable = [
        'workflow_template_id', 'version_no', 'document_type_id', 'category_id',
        'organizational_unit_id', 'trigger_event', 'status', 'published_by_user_id', 'published_at',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkflowTemplate::class, 'workflow_template_id');
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class);
    }

    public function organizationalUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class);
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_user_id');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(WorkflowTemplateStage::class)->orderBy('stage_order');
    }
}
