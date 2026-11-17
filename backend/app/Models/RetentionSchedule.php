<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RetentionSchedule extends Model
{
    protected $fillable = ['record_type', 'retention_duration', 'retention_unit', 'trigger_event', 'legal_basis', 'responsible_unit_id', 'disposition_method', 'approval_authority', 'is_active'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function records(): HasMany { return $this->hasMany(OperationalRecord::class); }
}
