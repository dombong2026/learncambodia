<?php

use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CefrController;
use App\Http\Controllers\InformationController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Workspace\AdminCategoryController;
use App\Http\Controllers\Workspace\AdminRecordsController;
use App\Http\Controllers\Workspace\DeveloperController;
use App\Http\Controllers\Workspace\DeveloperTokenController;
use App\Http\Controllers\Workspace\ProfileController;
use App\Http\Controllers\Workspace\StudentResourcesController;
use App\Http\Controllers\Workspace\WorkspaceController;
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
    Route::post('/logout', [SessionController::class, 'logout'])->name('logout');

    Route::middleware('role:admin')->prefix('dashboard')->name('admin.')->group(function (): void {
        Route::get('/', [WorkspaceController::class, 'admin'])->name('overview');
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::patch('/profile/password', [ProfileController::class, 'password'])->middleware('throttle:5,1')->name('profile.password');
        Route::get('/users', [AdminRecordsController::class, 'users'])->name('users.index');
        Route::get('/courses', [AdminRecordsController::class, 'courses'])->name('courses.index');
        Route::get('/cefr', [AdminRecordsController::class, 'cefr'])->name('cefr.index');
        Route::get('/categories', [AdminCategoryController::class, 'index'])->name('categories.index');
        Route::get('/categories/create', [AdminCategoryController::class, 'create'])->name('categories.create');
        Route::post('/categories', [AdminCategoryController::class, 'store'])->name('categories.store');
        Route::get('/categories/{category}/edit', [AdminCategoryController::class, 'edit'])->name('categories.edit');
        Route::patch('/categories/{category}', [AdminCategoryController::class, 'update'])->name('categories.update');
        Route::get('/manage/{section}/{item}', [WorkspaceController::class, 'module'])->name('module');
    });

    Route::middleware('role:student')->prefix('account')->name('account.')->group(function (): void {
        Route::get('/', [WorkspaceController::class, 'student'])->name('overview');
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::patch('/profile/password', [ProfileController::class, 'password'])->middleware('throttle:5,1')->name('profile.password');
        Route::get('/categories', [StudentResourcesController::class, 'categories'])->name('categories');
        Route::get('/cefr', [StudentResourcesController::class, 'cefr'])->name('cefr');
        Route::get('/help', [StudentResourcesController::class, 'help'])->name('help');
        Route::get('/manage/{section}/{item}', [WorkspaceController::class, 'module'])->name('module');
    });

    Route::middleware('role:developer')->prefix('developer')->name('developer.')->group(function (): void {
        Route::get('/', [WorkspaceController::class, 'developer'])->name('overview');
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::patch('/profile/password', [ProfileController::class, 'password'])->middleware('throttle:5,1')->name('profile.password');
        Route::get('/apis', [DeveloperController::class, 'apis'])->name('apis');
        Route::get('/usage', [DeveloperController::class, 'usage'])->name('usage');
        Route::get('/tokens', [DeveloperTokenController::class, 'index'])->name('tokens.index');
        Route::post('/tokens', [DeveloperTokenController::class, 'store'])->middleware('throttle:3,1')->name('tokens.store');
        Route::delete('/tokens/{token}', [DeveloperTokenController::class, 'destroy'])->middleware('throttle:10,1')->whereNumber('token')->name('tokens.destroy');
        Route::get('/manage/{section}/{item}', [WorkspaceController::class, 'module'])->name('module');
    });
});
