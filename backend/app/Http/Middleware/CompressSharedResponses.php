<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CompressSharedResponses
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if (config('app.env') !== 'ngrok' || ! str_contains((string) $request->header('Accept-Encoding'), 'gzip')) {
            return $response;
        }

        $contentType = strtolower((string) $response->headers->get('Content-Type'));
        $content = $response->getContent();
        if ($response->isRedirection() || $response->isEmpty() || $response->headers->has('Content-Encoding')
            || str_contains((string) $response->headers->get('Content-Disposition'), 'attachment')
            || ! is_string($content) || strlen($content) < 512
            || ! preg_match('#^(text/|application/(json|javascript|xml|x-javascript))#', $contentType)) {
            return $response;
        }

        $compressed = gzencode($content, 6);
        if ($compressed === false) return $response;

        $response->setContent($compressed);
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Vary', 'Accept-Encoding', false);
        $response->headers->remove('Content-Length');
        return $response;
    }
}
