<?php

use App\Http\Controllers\Workspace\AdminOpenAiController;
use Illuminate\Support\Facades\Route;

Route::prefix('system-settings/openai')->name('system-settings.openai.')->controller(AdminOpenAiController::class)->group(function (): void {
    Route::get('/', 'edit')->name('edit');
    Route::middleware(['password.confirm', 'throttle:6,1'])->group(function (): void {
        Route::get('/authorize', 'authorizeSettings')->name('authorize');
        Route::put('/', 'save')->name('save');
        Route::delete('/', 'remove')->name('remove');
        Route::post('/test', 'test')->name('test');
    });
});
Route::post('/course-builder/openai/draft', [AdminOpenAiController::class, 'draft'])
    ->middleware('throttle:6,1,openai-authoring')->name('course-builder.openai.draft');
