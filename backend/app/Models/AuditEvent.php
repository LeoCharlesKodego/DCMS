<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AuditEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'target_type', 'target_id', 'document_id', 'actor_user_id', 'event_type',
        'from_state', 'to_state', 'occurred_at', 'reason', 'metadata',
    ];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'metadata' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit events are append-only.'));
        static::deleting(fn () => throw new LogicException('Audit events are append-only.'));
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
