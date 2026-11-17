<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRoleAssignment extends Model
{
    protected $fillable = ['user_id', 'role_id', 'organizational_unit_id', 'work_scope', 'valid_from', 'valid_until'];

    protected function casts(): array
    {
        return ['valid_from' => 'date:Y-m-d', 'valid_until' => 'date:Y-m-d'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function organizationalUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class);
    }
}
