<?php

use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CefrController;
use App\Http\Controllers\InformationController;
use App\Http\Controllers\PublicContactController;
use App\Http\Controllers\PublicAuthorController;
use App\Http\Controllers\LearningHubController;
use App\Http\Controllers\Workspace\AdminContactInquiryController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\PublicCourseController;
use App\Http\Controllers\PublicArticleController;
use App\Http\Controllers\PublicPeopleController;
use App\Http\Controllers\Workspace\PublicProfileController;
use App\Http\Controllers\Workspace\AdminPublicProfileController;
use App\Http\Controllers\Workspace\AdminCategoryController;
use App\Http\Controllers\Workspace\AdminRecordsController;
use App\Http\Controllers\Workspace\AdminCourseController;
use App\Http\Controllers\Workspace\AdminCourseRevisionController;
use App\Http\Controllers\Workspace\AdminCurriculumController;
use App\Http\Controllers\Workspace\AdminAppearanceController;
use App\Http\Controllers\Workspace\AdminReviewController;
use App\Http\Controllers\Workspace\StudentEnrollmentController;
use App\Http\Controllers\Workspace\StudentSavedCourseController;
use App\Http\Controllers\Workspace\StudentPlannerController;
use App\Http\Controllers\Workspace\StudentNoteController;
use App\Http\Controllers\Workspace\InstructorInsightsController;
use App\Http\Controllers\Workspace\InstructorFeedbackTemplateController;
use App\Http\Controllers\Workspace\StudentProgressController;
use App\Http\Controllers\Workspace\StudentPracticeController;
use App\Http\Controllers\Workspace\AdminQuizController;
use App\Http\Controllers\Workspace\AdminLearningReportController;
use App\Http\Controllers\Workspace\AdminEditorialController;
use App\Http\Controllers\Workspace\AdminEditorialReviewController;
use App\Http\Controllers\Workspace\DeveloperController;
use App\Http\Controllers\Workspace\DeveloperTokenController;
use App\Http\Controllers\Workspace\ProfileController;
use App\Http\Controllers\Workspace\StudentResourcesController;
use App\Http\Controllers\Workspace\WorkspaceController;
use App\Http\Controllers\Workspace\AdminInstructorController;
use App\Http\Controllers\Workspace\AdminHomeworkReviewController;
use App\Http\Controllers\Workspace\AdminAcademicController;
use App\Http\Controllers\Workspace\InstructorTeachingController;
use App\Http\Controllers\Workspace\InstructorHomeworkController;
use App\Http\Controllers\Workspace\InstructorGradingController;
use App\Http\Controllers\Workspace\StudentHomeworkController;
use Illuminate\Support\Facades\Route;

Route::get('/install', [InstallController::class, 'index'])->name('install.index');
Route::post('/install/unlock', [InstallController::class, 'unlock'])
    ->middleware('throttle:5,1')->name('install.unlock');
Route::post('/install/complete', [InstallController::class, 'complete'])
    ->middleware('throttle:3,1')->name('install.complete');

Route::get('/', [CatalogController::class, 'home'])->name('home');
Route::get('/instructors', [PublicPeopleController::class, 'instructors'])->name('instructors.index');
Route::get('/instructors/{profile}', [PublicPeopleController::class, 'instructor'])->name('instructors.show');
Route::get('/students', [PublicPeopleController::class, 'students'])->name('students.index');
Route::get('/students/{profile}', [PublicPeopleController::class, 'student'])->name('students.show');
Route::get('/courses', [PublicCourseController::class, 'index'])->name('courses.index');
Route::get('/courses/{course:slug}', [PublicCourseController::class, 'show'])->name('courses.show');
Route::get('/library', [LearningHubController::class,'library'])->name('learning.library');
Route::get('/reading', [LearningHubController::class,'reading'])->name('learning.reading');
Route::get('/exam-catalog', [LearningHubController::class,'exams'])->name('learning.exams');
Route::get('/exam-catalog/{course:slug}', [LearningHubController::class,'exam'])->name('learning.exam.show');
Route::get('/recommendations', [LearningHubController::class,'recommendations'])->name('learning.recommendations');
Route::get('/leaderboard', [LearningHubController::class,'leaderboard'])->name('learning.leaderboard');
Route::get('/course-watch', [LearningHubController::class,'watch'])->name('learning.watch');
Route::get('/upload-studio', [LearningHubController::class,'studio'])->name('learning.studio');
Route::get('/authors', [PublicAuthorController::class, 'index'])->name('authors.index');
Route::get('/authors/{profile}', [PublicAuthorController::class, 'show'])->name('authors.show');
Route::get('/articles', [PublicArticleController::class, 'index'])->name('articles.index');
Route::get('/articles/{article}', [PublicArticleController::class, 'show'])->name('articles.show');
Route::get('/categories', [CatalogController::class, 'index'])->name('categories.index');
Route::get('/categories/{category}', [CatalogController::class, 'show'])->name('categories.show');
Route::get('/cefr', [CefrController::class, 'index'])->name('cefr.index');
Route::get('/information/{page}', [InformationController::class, 'show'])->name('information.show');
Route::post('/contact', [PublicContactController::class, 'store'])
    ->middleware('throttle:5,1')->name('contact.store');
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
        Route::get('/contact-inquiries', [AdminContactInquiryController::class, 'index'])->name('contact-inquiries.index');
        Route::patch('/contact-inquiries/{inquiry}', [AdminContactInquiryController::class, 'update'])
            ->middleware('throttle:30,1')->name('contact-inquiries.update');
        Route::get('/courses', [AdminCourseController::class, 'index'])->name('courses.index');
        Route::get('/instructors', [AdminInstructorController::class, 'index'])->name('instructors.index');
        Route::get('/public-profiles', [AdminPublicProfileController::class, 'index'])->name('public-profiles.index');
        Route::post('/public-profiles/{profile}/review', [AdminPublicProfileController::class, 'review'])
            ->middleware('throttle:12,1')->name('public-profiles.review');
        Route::post('/instructors/assign', [AdminInstructorController::class, 'assign'])
            ->middleware('throttle:10,1')->name('instructors.assign');
        Route::patch('/instructors/{assignment}/revoke', [AdminInstructorController::class, 'revoke'])
            ->name('instructors.revoke');
        Route::get('/academic/gradebook', [AdminAcademicController::class, 'gradebook'])->name('academic.gradebook');
        Route::get('/academic/evidence', [AdminAcademicController::class, 'evidence'])->name('academic.evidence');
        Route::post('/academic/evidence/enrollments/{enrollment}', [AdminAcademicController::class, 'request'])
            ->middleware('throttle:10,1')->name('academic.request');
        Route::post('/academic/evidence/reviews/{review}', [AdminAcademicController::class, 'review'])
            ->name('academic.review');
        Route::get('/homework/reviews', [AdminHomeworkReviewController::class, 'index'])->name('homework.reviews');
        Route::post('/homework/reviews/{assignment}', [AdminHomeworkReviewController::class, 'review'])
            ->name('homework.review');
        Route::get('/practice-quizzes', [AdminQuizController::class, 'index'])->name('quizzes.index');
        Route::get('/learning-report', [AdminLearningReportController::class, 'index'])->name('learning.report');

        Route::prefix('course-builder')->name('course-builder.')->group(function (): void {
            Route::get('/create', [AdminCourseController::class, 'create'])->name('create');
            Route::post('/', [AdminCourseController::class, 'store'])->name('store');
            Route::get('/{course}/edit', [AdminCourseController::class, 'edit'])->name('edit');
            Route::patch('/{course}', [AdminCourseController::class, 'update'])->name('update');
            Route::post('/{course}/submit', [AdminCourseController::class, 'submit'])->name('submit');
            Route::post('/{course}/revise', [AdminCourseRevisionController::class, 'store'])
                ->middleware('throttle:3,1')->name('revise');

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
            Route::get('/reviews', [AdminEditorialReviewController::class, 'index'])->name('reviews');
            Route::post('/reviews/{entry}', [AdminEditorialReviewController::class, 'review'])->name('review');
            Route::post('/{entry}/submit', [AdminEditorialReviewController::class, 'submit'])->name('submit');
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
        Route::get('/public-profile', [PublicProfileController::class, 'edit'])->name('public-profile.edit');
        Route::post('/public-profile', [PublicProfileController::class, 'save'])
            ->middleware('throttle:12,1')->name('public-profile.save');
        Route::post('/public-profile/submit', [PublicProfileController::class, 'submit'])
            ->middleware('throttle:6,1')->name('public-profile.submit');
        Route::post('/public-profile/withdraw', [PublicProfileController::class, 'withdraw'])
            ->middleware('throttle:6,1')->name('public-profile.withdraw');
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::patch('/profile/password', [ProfileController::class, 'password'])->middleware('throttle:5,1')->name('profile.password');
        Route::get('/categories', [StudentResourcesController::class, 'categories'])->name('categories');
        Route::get('/cefr', [StudentResourcesController::class, 'cefr'])->name('cefr');
        Route::get('/help', [StudentResourcesController::class, 'help'])->name('help');
        Route::get('/courses', [StudentEnrollmentController::class, 'catalog'])->name('courses.index');
        Route::get('/enrollments', [StudentEnrollmentController::class, 'index'])->name('enrollments.index');
        Route::get('/saved-courses', [StudentSavedCourseController::class, 'index'])->name('saved-courses.index');
        Route::post('/saved-courses/{course}', [StudentSavedCourseController::class, 'store'])
            ->middleware('throttle:20,1')->name('saved-courses.store');
        Route::delete('/saved-courses/{course}', [StudentSavedCourseController::class, 'destroy'])
            ->middleware('throttle:20,1')->name('saved-courses.destroy');
        Route::post('/courses/{course}/enroll', [StudentEnrollmentController::class, 'store'])->middleware('throttle:10,1')->name('enrollments.store');
        Route::delete('/enrollments/{enrollment}', [StudentEnrollmentController::class, 'cancel'])->name('enrollments.cancel');
        Route::get('/enrollments/{enrollment}', [StudentEnrollmentController::class, 'show'])->name('enrollments.show');
        Route::get('/notes', [StudentNoteController::class, 'index'])->name('notes.index');
        Route::post('/enrollments/{enrollment}/lessons/{lesson}/notes', [StudentNoteController::class, 'store'])
            ->middleware('throttle:20,1')->name('notes.store');
        Route::patch('/notes/{note}', [StudentNoteController::class, 'update'])
            ->middleware('throttle:20,1')->name('notes.update');
        Route::delete('/notes/{note}', [StudentNoteController::class, 'destroy'])->name('notes.destroy');
        Route::get('/planner', [StudentPlannerController::class, 'index'])->name('planner.index');
        Route::post('/planner/goals', [StudentPlannerController::class, 'storeGoal'])->middleware('throttle:20,1')->name('planner.goals.store');
        Route::patch('/planner/goals/{goal}', [StudentPlannerController::class, 'updateGoal'])->middleware('throttle:20,1')->name('planner.goals.update');
        Route::delete('/planner/goals/{goal}', [StudentPlannerController::class, 'destroyGoal'])->name('planner.goals.destroy');
        Route::post('/planner/goals/{goal}/tasks', [StudentPlannerController::class, 'storeTask'])->middleware('throttle:30,1')->name('planner.tasks.store');
        Route::patch('/planner/tasks/{task}', [StudentPlannerController::class, 'toggleTask'])->name('planner.tasks.toggle');
        Route::delete('/planner/tasks/{task}', [StudentPlannerController::class, 'destroyTask'])->name('planner.tasks.destroy');
        Route::post('/planner/sessions', [StudentPlannerController::class, 'storeSession'])->middleware('throttle:20,1')->name('planner.sessions.store');
        Route::delete('/planner/sessions/{session}', [StudentPlannerController::class, 'destroySession'])->name('planner.sessions.destroy');
        Route::get('/progress', [StudentProgressController::class, 'index'])->name('progress.index');
        Route::get('/enrollments/{enrollment}/watch/{lesson}', [LearningHubController::class, 'lesson'])
            ->name('lessons.watch');
        Route::get('/homework', [StudentHomeworkController::class, 'index'])->name('homework.index');
        Route::get('/homework/submissions', [StudentHomeworkController::class, 'submissions'])->name('homework.submissions');
        Route::get('/homework/{assignment}', [StudentHomeworkController::class, 'show'])->name('homework.show');
        Route::post('/homework/{assignment}/submit', [StudentHomeworkController::class, 'submit'])
            ->middleware('throttle:6,1')->name('homework.submit');
        Route::get('/homework/submissions/{submission}/review', [StudentHomeworkController::class, 'result'])
            ->name('homework.result');
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

    Route::middleware('role:instructor')->prefix('instructor')->name('instructor.')->group(function (): void {
        Route::get('/', [WorkspaceController::class, 'instructor'])->name('overview');
        Route::get('/public-profile', [PublicProfileController::class, 'edit'])->name('public-profile.edit');
        Route::post('/public-profile', [PublicProfileController::class, 'save'])
            ->middleware('throttle:12,1')->name('public-profile.save');
        Route::post('/public-profile/submit', [PublicProfileController::class, 'submit'])
            ->middleware('throttle:6,1')->name('public-profile.submit');
        Route::post('/public-profile/withdraw', [PublicProfileController::class, 'withdraw'])
            ->middleware('throttle:6,1')->name('public-profile.withdraw');
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::patch('/profile/password', [ProfileController::class, 'password'])
            ->middleware('throttle:5,1')->name('profile.password');
        Route::get('/courses', [InstructorTeachingController::class, 'index'])->name('courses.index');
        Route::get('/courses/{course}', [InstructorTeachingController::class, 'show'])->name('courses.show');
        Route::get('/insights', [InstructorInsightsController::class, 'index'])->name('insights.index');
        Route::get('/feedback-templates', [InstructorFeedbackTemplateController::class, 'index'])->name('feedback-templates.index');
        Route::post('/feedback-templates', [InstructorFeedbackTemplateController::class, 'store'])
            ->middleware('throttle:12,1')->name('feedback-templates.store');
        Route::patch('/feedback-templates/{template}', [InstructorFeedbackTemplateController::class, 'update'])
            ->middleware('throttle:12,1')->name('feedback-templates.update');
        Route::delete('/feedback-templates/{template}', [InstructorFeedbackTemplateController::class, 'destroy'])
            ->name('feedback-templates.destroy');
        Route::get('/assignments', [InstructorHomeworkController::class, 'index'])->name('homework.index');
        Route::post('/courses/{course}/assignments', [InstructorHomeworkController::class, 'store'])->name('homework.store');
        Route::get('/assignments/{assignment}/edit', [InstructorHomeworkController::class, 'edit'])->name('homework.edit');
        Route::patch('/assignments/{assignment}', [InstructorHomeworkController::class, 'update'])->name('homework.update');
        Route::post('/assignments/{assignment}/submit', [InstructorHomeworkController::class, 'submit'])
            ->name('homework.submit');
        Route::get('/submissions', [InstructorGradingController::class, 'index'])->name('grading.index');
        Route::get('/submissions/{submission}', [InstructorGradingController::class, 'show'])->name('grading.show');
        Route::post('/submissions/{submission}/review', [InstructorGradingController::class, 'review'])
            ->middleware('throttle:30,1')->name('grading.review');
        Route::get('/guidance', [InstructorTeachingController::class, 'guidance'])->name('guidance');
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
