<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ExternalReference extends Model
{
    protected $fillable = ['title', 'issuing_body', 'reference_number', 'url', 'published_at', 'last_reviewed_at', 'review_due_at', 'status', 'notes', 'created_by_user_id'];

    protected function casts(): array
    {
        return ['published_at' => 'date:Y-m-d', 'last_reviewed_at' => 'date:Y-m-d', 'review_due_at' => 'date:Y-m-d'];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'document_external_references')->withTimestamps();
    }
}
