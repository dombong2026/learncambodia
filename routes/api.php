<?php

use App\Http\Controllers\Api\CatalogApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum', 'developer.token', 'throttle:60,1'])
    ->group(function (): void {
        Route::get('/categories', [CatalogApiController::class, 'categories']);
        Route::get('/cefr-levels', [CatalogApiController::class, 'levels']);
        Route::get('/courses', [CatalogApiController::class, 'courses']);
    });
