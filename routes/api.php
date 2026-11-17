<?php

use App\Http\Controllers\Api\CatalogApiController;
use App\Http\Controllers\CatalogController;
use Illuminate\Support\Facades\Route;

Route::get('/navigation/categories', [CatalogController::class, 'navigationCategories'])
    ->name('navigation.categories');

Route::prefix('v1')->middleware(['auth:sanctum', 'developer.token', 'throttle:60,1'])
    ->group(function (): void {
        Route::get('/categories', [CatalogApiController::class, 'categories']);
        Route::get('/cefr-levels', [CatalogApiController::class, 'levels']);
        Route::get('/courses', [CatalogApiController::class, 'courses']);
    });
