<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowTemplate extends Model
{
    protected $fillable = ['template_key', 'name', 'description', 'is_active', 'created_by_user_id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(WorkflowTemplateVersion::class)->orderByDesc('version_no');
    }
}
