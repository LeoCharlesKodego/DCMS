<?php

namespace App\Services;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AuthorizationService
{
    public function allows(User $user, string $permissionCode, ?int $organizationalUnitId = null): bool
    {
        if (! $user->is_active) {
            return false;
        }

        $today = Institution::localDate();

        $query = DB::table('user_role_assignments as assignments')
            ->join('roles', 'roles.id', '=', 'assignments.role_id')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'roles.id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('assignments.user_id', $user->id)
            ->where('roles.is_active', true)
            ->where('permissions.code', $permissionCode)
            ->whereDate('assignments.valid_from', '<=', $today)
            ->where(fn ($dateQuery) => $dateQuery->whereNull('assignments.valid_until')->orWhereDate('assignments.valid_until', '>=', $today))
            ->where(function ($scopeQuery) use ($organizationalUnitId) {
                $scopeQuery->where('role_permissions.scope_type', 'institution');
                if ($organizationalUnitId !== null) {
                    $scopeQuery->orWhere(fn ($unitQuery) => $unitQuery
                        ->where('role_permissions.scope_type', 'organizational_unit')
                        ->where('assignments.organizational_unit_id', $organizationalUnitId));
                }
            });

        return $query->exists();
    }

    /** @return list<int> */
    public function organizationalUnitIdsFor(User $user, string $permissionCode): array
    {
        if (! $user->is_active) {
            return [];
        }

        $today = Institution::localDate();

        return DB::table('user_role_assignments as assignments')
            ->join('roles', 'roles.id', '=', 'assignments.role_id')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'roles.id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('assignments.user_id', $user->id)
            ->whereNotNull('assignments.organizational_unit_id')
            ->where('roles.is_active', true)
            ->where('permissions.code', $permissionCode)
            ->where('role_permissions.scope_type', 'organizational_unit')
            ->whereDate('assignments.valid_from', '<=', $today)
            ->where(fn ($query) => $query->whereNull('assignments.valid_until')->orWhereDate('assignments.valid_until', '>=', $today))
            ->distinct()
            ->pluck('assignments.organizational_unit_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function hasActiveRoleAssignment(User $user, string $roleCode, int $organizationalUnitId): bool
    {
        if (! $user->is_active) {
            return false;
        }

        $today = Institution::localDate();

        return DB::table('user_role_assignments as assignments')
            ->join('roles', 'roles.id', '=', 'assignments.role_id')
            ->where('assignments.user_id', $user->id)
            ->where('assignments.organizational_unit_id', $organizationalUnitId)
            ->where('roles.code', $roleCode)
            ->where('roles.is_active', true)
            ->whereDate('assignments.valid_from', '<=', $today)
            ->where(fn ($query) => $query->whereNull('assignments.valid_until')->orWhereDate('assignments.valid_until', '>=', $today))
            ->exists();
    }
}
