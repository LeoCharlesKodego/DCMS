<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Document extends Model
{
    protected $fillable = [
        'document_type_id', 'category_id', 'classification_id', 'owner_user_id', 'owning_unit_id',
        'created_by_user_id', 'updated_by_user_id', 'registered_by_user_id', 'title',
        'current_revision_label', 'status', 'effective_date', 'retention_years', 'remarks', 'registered_at',
    ];

    protected function casts(): array
    {
        return ['effective_date' => 'date:Y-m-d', 'registered_at' => 'datetime'];
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class);
    }

    public function classification(): BelongsTo
    {
        return $this->belongsTo(DocumentClassification::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function owningUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'owning_unit_id');
    }

    public function masterCodeRegisterEntry(): HasOne
    {
        return $this->hasOne(MasterCodeRegisterEntry::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->orderBy('sequence_no');
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(AuditEvent::class)->orderByDesc('occurred_at');
    }

    public function externalReferences(): BelongsToMany
    {
        return $this->belongsToMany(ExternalReference::class, 'document_external_references')->withTimestamps();
    }
}
