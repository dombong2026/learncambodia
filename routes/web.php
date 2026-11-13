<?php

use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CefrController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InformationController;
use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogController::class, 'home'])->name('home');
Route::get('/categories', [CatalogController::class, 'index'])->name('categories.index');
Route::get('/categories/{category}', [CatalogController::class, 'show'])->name('categories.show');
Route::get('/cefr', [CefrController::class, 'index'])->name('cefr.index');
Route::get('/information/{page}', [InformationController::class, 'show'])->name('information.show');
Route::post('/language', [LocaleController::class, 'update'])->name('language.update');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [SessionController::class, 'loginForm'])->name('login');
    Route::post('/login', [SessionController::class, 'login'])->name('login.store');
    Route::get('/register', [SessionController::class, 'registerForm'])->name('register');
    Route::post('/register', [SessionController::class, 'register'])->middleware('throttle:5,1')->name('register.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [SessionController::class, 'logout'])->name('logout');
});
