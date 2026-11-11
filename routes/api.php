<?php

use App\Http\Controllers\Api\V1\Admin\UploadController;
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

// Dashboard (API-CONTRACT §4): everything behind the admin's Bearer token.
Route::prefix('admin')->name('admin.')->middleware('auth:sanctum')->group(function () {
    Route::post('uploads', [UploadController::class, 'store'])->name('uploads.store');
});
