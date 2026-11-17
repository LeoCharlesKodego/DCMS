<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalRecordHold extends Model
{
    protected $fillable = ['operational_record_id', 'reason', 'authority_reference', 'placed_by_user_id', 'placed_at', 'released_by_user_id', 'released_at', 'release_reason'];
    protected function casts(): array { return ['placed_at' => 'datetime', 'released_at' => 'datetime']; }
    public function record(): BelongsTo { return $this->belongsTo(OperationalRecord::class, 'operational_record_id'); }
}
