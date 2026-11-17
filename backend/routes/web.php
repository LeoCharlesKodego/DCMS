<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SharedFrontendController;

Route::get('/', function () {
    if (config('app.env') === 'ngrok') return app(SharedFrontendController::class)->index();
    return view('welcome');
});

Route::get('/assets/{path}', [SharedFrontendController::class, 'asset'])->where('path', '.*');
Route::get('/{path}', [SharedFrontendController::class, 'index'])
    ->where('path', '^(?!api(?:/|$)|assets(?:/|$)|up$).*$');
