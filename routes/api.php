<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes (prefix: /api/v1)
|--------------------------------------------------------------------------
|
| Endpoints follow the agreed contract:
| ../restaurant-template/.local-docs/API-CONTRACT.md
|
*/

Route::prefix('auth')->name('auth.')->group(function () {
    // Failed tries are limited inside LoginRequest (5 per minute per username + client IP).
    Route::post('login', [AuthController::class, 'login'])->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');
    });
});
