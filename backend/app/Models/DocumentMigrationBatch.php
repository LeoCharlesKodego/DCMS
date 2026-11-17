<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentMigrationBatch extends Model
{
    protected $fillable = ['name', 'source_description', 'status', 'created_by_user_id', 'closed_at'];

    protected function casts(): array
    {
        return ['closed_at' => 'datetime'];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DocumentMigrationItem::class);
    }
}
