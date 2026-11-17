<?php

namespace App\Http\Middleware;

use App\Services\AuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRolePermission
{
    public function handle(Request $request, Closure $next, string $permission, string $scope = 'institution'): Response
    {
        $unitId = null;
        if ($scope === 'organizational_unit') {
            $unit = $request->route('organizationalUnit') ?? $request->route('unit');
            $unitId = is_object($unit) ? ($unit->id ?? null) : $unit;
            $unitId ??= $request->input('organizational_unit_id');
            abort_if($unitId === null, 403, 'An organizational unit is required for this action.');
        }

        abort_unless(
            $request->user() && app(AuthorizationService::class)->allows(
                $request->user(),
                $permission,
                $unitId === null ? null : (int) $unitId,
            ),
            403,
            'Your active role assignment does not grant this action in the requested scope.',
        );

        return $next($request);
    }
}
