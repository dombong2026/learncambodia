<?php

use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CefrController;
use App\Http\Controllers\InformationController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Workspace\AdminCategoryController;
use App\Http\Controllers\Workspace\AdminRecordsController;
use App\Http\Controllers\Workspace\AdminCourseController;
use App\Http\Controllers\Workspace\AdminCurriculumController;
use App\Http\Controllers\Workspace\AdminAppearanceController;
use App\Http\Controllers\Workspace\AdminReviewController;
use App\Http\Controllers\Workspace\StudentEnrollmentController;
use App\Http\Controllers\Workspace\StudentProgressController;
use App\Http\Controllers\Workspace\StudentPracticeController;
use App\Http\Controllers\Workspace\AdminQuizController;
use App\Http\Controllers\Workspace\AdminLearningReportController;
use App\Http\Controllers\Workspace\AdminEditorialController;
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
        Route::get('/courses', [AdminCourseController::class, 'index'])->name('courses.index');
        Route::get('/practice-quizzes', [AdminQuizController::class, 'index'])->name('quizzes.index');
        Route::get('/learning-report', [AdminLearningReportController::class, 'index'])->name('learning.report');

        Route::prefix('course-builder')->name('course-builder.')->group(function (): void {
            Route::get('/create', [AdminCourseController::class, 'create'])->name('create');
            Route::post('/', [AdminCourseController::class, 'store'])->name('store');
            Route::get('/{course}/edit', [AdminCourseController::class, 'edit'])->name('edit');
            Route::patch('/{course}', [AdminCourseController::class, 'update'])->name('update');
            Route::post('/{course}/submit', [AdminCourseController::class, 'submit'])->name('submit');

            Route::post('/{course}/quizzes', [AdminQuizController::class, 'store'])->name('quizzes.store');
            Route::get('/{course}/quizzes/{quiz}/edit', [AdminQuizController::class, 'edit'])->name('quizzes.edit');
            Route::patch('/{course}/quizzes/{quiz}', [AdminQuizController::class, 'update'])->name('quizzes.update');
            Route::delete('/{course}/quizzes/{quiz}', [AdminQuizController::class, 'destroy'])->name('quizzes.destroy');
            Route::post('/{course}/quizzes/{quiz}/questions', [AdminQuizController::class, 'storeQuestion'])->name('quizzes.questions.store');
            Route::patch('/{course}/quizzes/{quiz}/questions/{question}', [AdminQuizController::class, 'updateQuestion'])->name('quizzes.questions.update');
            Route::delete('/{course}/quizzes/{quiz}/questions/{question}', [AdminQuizController::class, 'destroyQuestion'])->name('quizzes.questions.destroy');

            Route::post('/{course}/modules', [AdminCurriculumController::class, 'storeModule'])->name('modules.store');
            Route::patch('/{course}/modules/{module}', [AdminCurriculumController::class, 'updateModule'])->name('modules.update');
            Route::delete('/{course}/modules/{module}', [AdminCurriculumController::class, 'destroyModule'])->name('modules.destroy');
            Route::post('/{course}/modules/{module}/lessons', [AdminCurriculumController::class, 'storeLesson'])->name('lessons.store');
            Route::patch('/{course}/modules/{module}/lessons/{lesson}', [AdminCurriculumController::class, 'updateLesson'])->name('lessons.update');
            Route::delete('/{course}/modules/{module}/lessons/{lesson}', [AdminCurriculumController::class, 'destroyLesson'])->name('lessons.destroy');
        });

        Route::prefix('appearance')->name('appearance.')->group(function (): void {
            Route::get('/', [AdminAppearanceController::class, 'index'])->name('index');
            Route::get('/{section}', [AdminAppearanceController::class, 'edit'])->name('edit');
            Route::post('/{section}', [AdminAppearanceController::class, 'store'])->name('store');
            Route::post('/revisions/{revision}/submit', [AdminAppearanceController::class, 'submit'])->name('submit');
        });

        Route::get('/reviews', [AdminReviewController::class, 'index'])->name('reviews.index');
        Route::post('/reviews/courses/{course}', [AdminReviewController::class, 'course'])->name('reviews.course');
        Route::post('/reviews/appearance/{revision}', [AdminReviewController::class, 'appearance'])->name('reviews.appearance');
        Route::post('/reviews/enrollments/{enrollment}', [AdminReviewController::class, 'enrollment'])->name('reviews.enrollment');
        Route::get('/cefr', [AdminRecordsController::class, 'cefr'])->name('cefr.index');
        Route::get('/categories', [AdminCategoryController::class, 'index'])->name('categories.index');
        Route::get('/categories/create', [AdminCategoryController::class, 'create'])->name('categories.create');
        Route::post('/categories', [AdminCategoryController::class, 'store'])->name('categories.store');
        Route::get('/categories/{category}/edit', [AdminCategoryController::class, 'edit'])->name('categories.edit');
        Route::patch('/categories/{category}', [AdminCategoryController::class, 'update'])->name('categories.update');
        Route::prefix('editorial/{kind}')->name('editorial.')->group(function (): void {
            Route::get('/', [AdminEditorialController::class, 'index'])->name('index');
            Route::get('/create', [AdminEditorialController::class, 'create'])->name('create');
            Route::post('/', [AdminEditorialController::class, 'store'])->name('store');
            Route::get('/{entry}/edit', [AdminEditorialController::class, 'edit'])->name('edit');
            Route::patch('/{entry}', [AdminEditorialController::class, 'update'])->name('update');
        });
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
        Route::get('/courses', [StudentEnrollmentController::class, 'catalog'])->name('courses.index');
        Route::get('/enrollments', [StudentEnrollmentController::class, 'index'])->name('enrollments.index');
        Route::post('/courses/{course}/enroll', [StudentEnrollmentController::class, 'store'])->middleware('throttle:10,1')->name('enrollments.store');
        Route::delete('/enrollments/{enrollment}', [StudentEnrollmentController::class, 'cancel'])->name('enrollments.cancel');
        Route::get('/enrollments/{enrollment}', [StudentEnrollmentController::class, 'show'])->name('enrollments.show');
        Route::get('/progress', [StudentProgressController::class, 'index'])->name('progress.index');
        Route::post('/enrollments/{enrollment}/lessons/{lesson}/progress', [StudentProgressController::class, 'update'])
            ->middleware('throttle:30,1')->name('progress.update');
        Route::get('/practice', [StudentPracticeController::class, 'index'])->name('practice.index');
        Route::get('/practice/history', [StudentPracticeController::class, 'history'])->name('practice.history');
        Route::get('/enrollments/{enrollment}/quizzes/{quiz}', [StudentPracticeController::class, 'show'])->name('practice.show');
        Route::post('/enrollments/{enrollment}/quizzes/{quiz}/attempts', [StudentPracticeController::class, 'store'])
            ->middleware('throttle:6,1')->name('practice.store');
        Route::get('/enrollments/{enrollment}/quizzes/{quiz}/attempts/{attempt}', [StudentPracticeController::class, 'result'])
            ->name('practice.result');
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
