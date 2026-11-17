<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentDistribution extends Model
{
    protected $fillable = ['document_version_id', 'issued_by_user_id', 'issued_at'];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime'];
    }

    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(DistributionTarget::class)->orderBy('id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(DistributionRecipient::class)->orderBy('id');
    }
}
