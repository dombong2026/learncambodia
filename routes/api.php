<?php

use App\Http\Controllers\Api\CatalogApiController;
use App\Http\Controllers\CatalogController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\McpBridgeController;

Route::get('/mcp/auth-guide', [McpBridgeController::class, 'authGuide'])
    ->middleware('throttle:20,1')->name('mcp.auth-guide');
Route::post('/mcp', [McpBridgeController::class, 'handle'])
    ->middleware(['auth:sanctum','throttle:60,1'])->name('mcp.rpc');
Route::post('/mcp/media', [McpBridgeController::class, 'upload'])
    ->middleware(['auth:sanctum','throttle:15,1'])->name('mcp.media.upload');

Route::get('/navigation/categories', [CatalogController::class, 'navigationCategories'])
    ->name('navigation.categories');

Route::prefix('v1')->middleware(['auth:sanctum', 'developer.token', 'throttle:60,1'])
    ->group(function (): void {
        Route::get('/categories', [CatalogApiController::class, 'categories']);
        Route::get('/cefr-levels', [CatalogApiController::class, 'levels']);
        Route::get('/courses', [CatalogApiController::class, 'courses']);
    });
