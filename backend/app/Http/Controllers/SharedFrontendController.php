<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SharedFrontendController extends Controller
{
    public function index(): Response
    {
        abort_unless(config('app.env') === 'ngrok', 404);
        $path = base_path('../frontend/dist/index.html');
        if (! is_file($path)) throw new NotFoundHttpException('Production frontend build is missing.');
        return response(file_get_contents($path), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    // Extension-based MIME map: libmagic reports CSS as text/plain, so never rely on detection here.
    private const ASSET_MIME_TYPES = [
        'css' => 'text/css',
        'js' => 'text/javascript',
        'mjs' => 'text/javascript',
        'json' => 'application/json',
        'map' => 'application/json',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
        'txt' => 'text/plain',
        'pdf' => 'application/pdf',
    ];

    public function asset(string $path): Response
    {
        abort_unless(config('app.env') === 'ngrok', 404);
        $root = realpath(base_path('../frontend/dist/assets'));
        $file = realpath(base_path('../frontend/dist/assets/'.$path));
        if (! $root || ! $file || ! str_starts_with($file, $root.DIRECTORY_SEPARATOR) || ! is_file($file)) {
            throw new NotFoundHttpException();
        }
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mime = self::ASSET_MIME_TYPES[$extension] ?? 'application/octet-stream';
        return response(file_get_contents($file), 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
