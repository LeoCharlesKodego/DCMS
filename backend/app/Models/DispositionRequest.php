<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispositionRequest extends Model
{
    protected $fillable = ['operational_record_id', 'requested_by_user_id', 'reason', 'status', 'decided_by_user_id', 'decided_at', 'decision_reason', 'executed_by_user_id', 'executed_at', 'execution_evidence'];
    protected function casts(): array { return ['decided_at' => 'datetime', 'executed_at' => 'datetime']; }
    public function record(): BelongsTo { return $this->belongsTo(OperationalRecord::class, 'operational_record_id'); }
}
