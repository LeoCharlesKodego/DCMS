<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DistributionRecipient extends Model
{
    protected $fillable = [
        'document_distribution_id', 'user_id', 'recipient_name_at_issue', 'acknowledgment_required',
        'due_at', 'acknowledgment_status', 'acknowledged_by_user_id', 'acknowledged_at',
        'exempted_by_user_id', 'exempted_at', 'exemption_reason',
    ];

    protected function casts(): array
    {
        return [
            'acknowledgment_required' => 'boolean',
            'due_at' => 'date:Y-m-d',
            'acknowledged_at' => 'datetime',
            'exempted_at' => 'datetime',
        ];
    }

    public function distribution(): BelongsTo
    {
        return $this->belongsTo(DocumentDistribution::class, 'document_distribution_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by_user_id');
    }

    public function exemptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exempted_by_user_id');
    }

    public function sources(): HasMany
    {
        return $this->hasMany(DistributionRecipientSource::class);
    }
}
