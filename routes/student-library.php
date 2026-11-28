<?php

use App\Http\Controllers\LibraryController;
use App\Http\Controllers\Workspace\AdminLibraryController;
use App\Http\Controllers\Workspace\StudentLibraryController;
use Illuminate\Support\Facades\Route;

Route::get('/library', [LibraryController::class, 'index'])->name('learning.library');
Route::prefix('library/{item}')->where(['item' => '[0-7][0-9A-HJKMNP-TV-Z]{25}'])->name('library.')->controller(LibraryController::class)->group(function (): void {
    Route::get('/', 'show')->name('show');
    Route::get('/read', 'reader')->name('reader');
    Route::get('/content', 'content')->name('content');
    Route::get('/download', 'download')->name('download');
    Route::get('/cover', 'cover')->name('cover');
});

Route::middleware(['auth', 'role:student'])->prefix('account/library')->name('account.library.')
    ->controller(StudentLibraryController::class)->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/{item}/save', 'save')->whereUlid('item')->middleware('throttle:30,1')->name('save');
        Route::delete('/{item}/save', 'unsave')->whereUlid('item')->middleware('throttle:30,1')->name('unsave');
        Route::post('/{item}/borrow', 'borrow')->whereUlid('item')->middleware('throttle:15,1')->name('borrow');
        Route::patch('/loans/{loan}/return', 'returnItem')->whereNumber('loan')->middleware('throttle:15,1')->name('return');
    });

Route::middleware(['auth', 'role:admin'])->prefix('dashboard/library')->name('admin.library.')
    ->controller(AdminLibraryController::class)->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::get('/loans', 'loans')->name('loans');
        Route::post('/', 'store')->middleware('throttle:12,1')->name('store');
        Route::get('/{item}/edit', 'edit')->whereUlid('item')->name('edit');
        Route::patch('/{item}', 'update')->whereUlid('item')->middleware('throttle:12,1')->name('update');
        Route::get('/{item}/preview', 'reader')->whereUlid('item')->name('preview');
        Route::get('/{item}/file', 'file')->whereUlid('item')->name('file');
    });
