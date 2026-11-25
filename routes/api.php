<?php

use App\Http\Controllers\Api\CatalogApiController;
use App\Http\Controllers\CatalogController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\McpBridgeController;

// Passport OAuth remains isolated from the already-accepted Sanctum MCP route.
// The middleware itself returns 404 until an audited future release enables it.
Route::post('/mcp/oauth/token', [\App\Http\Controllers\Api\McpOAuthAccessTokenController::class, 'issueToken'])
    ->middleware([\App\Http\Middleware\EnsureMcpOAuthApiEnabled::class, 'mcp.oauth.params:token', 'throttle:15,1'])
    ->name('mcp.oauth.token');
Route::post('/mcp/oauth', [\App\Http\Controllers\Api\McpOAuthReadOnlyController::class, 'handle'])
    ->middleware([\App\Http\Middleware\EnsureMcpOAuthApiEnabled::class, 'auth:mcp_oauth', 'mcp.oauth.read', 'throttle:60,1'])
    ->name('mcp.oauth.rpc');

// Streamable HTTP without a standalone SSE stream. No data is returned.
Route::get('/mcp/oauth', static fn () => response()->json(
    ['error' => 'method_not_allowed'], 405, ['Allow' => 'POST', 'Cache-Control' => 'no-store']
))->middleware([\App\Http\Middleware\EnsureMcpOAuthApiEnabled::class, 'throttle:60,1'])
    ->name('mcp.oauth.stream.unsupported');

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
