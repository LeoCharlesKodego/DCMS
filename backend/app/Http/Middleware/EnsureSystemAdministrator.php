<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSystemAdministrator
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->is_system_admin, 403, 'System administrator access is required.');

        return $next($request);
    }
}
