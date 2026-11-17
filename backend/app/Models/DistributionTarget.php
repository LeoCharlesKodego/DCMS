<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DistributionTarget extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'document_distribution_id', 'target_type', 'organizational_unit_id', 'role_id', 'user_id',
        'target_label_snapshot', 'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function distribution(): BelongsTo
    {
        return $this->belongsTo(DocumentDistribution::class, 'document_distribution_id');
    }

    public function organizationalUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recipientSources(): HasMany
    {
        return $this->hasMany(DistributionRecipientSource::class, 'distribution_target_id');
    }
}
