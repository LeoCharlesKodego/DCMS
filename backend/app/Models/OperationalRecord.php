<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperationalRecord extends Model
{
    protected $fillable = ['record_type', 'title', 'reference_number', 'retention_schedule_id', 'owning_unit_id', 'created_on', 'retention_triggered_on', 'status', 'created_by_user_id'];
    protected function casts(): array { return ['created_on' => 'date:Y-m-d', 'retention_triggered_on' => 'date:Y-m-d']; }
    public function schedule(): BelongsTo { return $this->belongsTo(RetentionSchedule::class, 'retention_schedule_id'); }
    public function holds(): HasMany { return $this->hasMany(OperationalRecordHold::class); }
    public function dispositionRequests(): HasMany { return $this->hasMany(DispositionRequest::class); }
}
