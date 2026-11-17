<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = ['code', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(UserRoleAssignment::class);
    }

    public function permissions(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')
            ->withPivot('scope_type')
            ->withTimestamps();
    }
}
