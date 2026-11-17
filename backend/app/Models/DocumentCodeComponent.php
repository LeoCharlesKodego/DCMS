<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentCodeComponent extends Model
{
    protected $fillable = [
        'source_key', 'parent_service_id', 'component_type', 'code', 'name',
        'source_group', 'source_flags', 'is_conflicted', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_conflicted' => 'boolean', 'is_active' => 'boolean'];
    }

    public function parentService(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_service_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_service_id');
    }
}
