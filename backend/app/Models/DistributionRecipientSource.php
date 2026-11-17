<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistributionRecipientSource extends Model
{
    public $timestamps = false;

    protected $fillable = ['distribution_recipient_id', 'distribution_target_id', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(DistributionRecipient::class, 'distribution_recipient_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(DistributionTarget::class, 'distribution_target_id');
    }
}
