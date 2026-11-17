<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MasterCodeRegisterEntry extends Model
{
    protected $fillable = [
        'document_id', 'service_component_id', 'section_component_id', 'unit_component_id',
        'document_type_component_id', 'code_prefix', 'sequence_number', 'base_code',
        'requested_by_user_id', 'assigned_by_user_id', 'requested_at', 'assigned_at',
    ];

    protected function casts(): array
    {
        return ['requested_at' => 'datetime', 'assigned_at' => 'datetime'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function serviceComponent(): BelongsTo
    {
        return $this->belongsTo(DocumentCodeComponent::class, 'service_component_id');
    }

    public function sectionComponent(): BelongsTo
    {
        return $this->belongsTo(DocumentCodeComponent::class, 'section_component_id');
    }

    public function unitComponent(): BelongsTo
    {
        return $this->belongsTo(DocumentCodeComponent::class, 'unit_component_id');
    }

    public function documentTypeComponent(): BelongsTo
    {
        return $this->belongsTo(DocumentCodeComponent::class, 'document_type_component_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }
}
