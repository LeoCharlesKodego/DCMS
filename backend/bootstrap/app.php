<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

$application = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        if (getenv('APP_ENV') === 'ngrok') {
            $middleware->trustProxies(at: getenv('TRUSTED_PROXIES') ?: '127.0.0.1,::1');
            $allowedHostPattern = getenv('APP_ALLOWED_HOST_PATTERN');
            if ($allowedHostPattern) {
                $middleware->trustHosts(at: [$allowedHostPattern], subdomains: false);
            }
            $middleware->append(\App\Http\Middleware\CompressSharedResponses::class);
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

if (getenv('APP_ENV') === 'ngrok' && getenv('APP_STORAGE_PATH')) {
    $application->useStoragePath($application->basePath(getenv('APP_STORAGE_PATH')));
}

return $application;
