<?php

use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\Workspace\AdminExaminationController;
use App\Http\Controllers\Workspace\AssessmentAccessController;
use App\Http\Controllers\Workspace\StudentExaminationController;
use App\Http\Controllers\EditorMediaController;
use App\Http\Controllers\PublicHelpController;
use App\Http\Controllers\PublicDocumentController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Auth\AccountPasskeyController;
use App\Http\Controllers\Auth\AccountSecurityController;
use App\Http\Controllers\Auth\GoogleSignInController;
use Laravel\Passkeys\Http\Controllers\PasskeyLoginController;
use Laravel\Passkeys\Http\Controllers\PasskeyRegistrationController;
use App\Http\Controllers\Auth\PasswordRecoveryController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\BrandAssetController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CertificateVerificationController;
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
use App\Http\Controllers\Workspace\AdminLearningRecordController;
use App\Http\Controllers\Workspace\AdminCourseRevisionController;
use App\Http\Controllers\Workspace\AdminCurriculumController;
use App\Http\Controllers\Workspace\AdminAppearanceController;
use App\Http\Controllers\Workspace\AdminBrandingController;
use App\Http\Controllers\Workspace\AdminSystemSettingsController;
use App\Http\Controllers\Workspace\AdminReviewController;
use App\Http\Controllers\Workspace\StudentEnrollmentController;
use App\Http\Controllers\Workspace\StudentSavedCourseController;
use App\Http\Controllers\Workspace\StudentPlannerController;
use App\Http\Controllers\Workspace\StudentNoteController;
use App\Http\Controllers\Workspace\InstructorInsightsController;
use App\Http\Controllers\Workspace\InstructorFeedbackTemplateController;
use App\Http\Controllers\Workspace\AdminCurriculumHistoryController;
use App\Http\Controllers\Workspace\StudentProgressController;
use App\Http\Controllers\Workspace\StudentLessonQuizController;
use App\Http\Controllers\Workspace\StudentPracticeController;
use App\Http\Controllers\Workspace\AdminQuizController;
use App\Http\Controllers\Workspace\AdminLearningReportController;
use App\Http\Controllers\Workspace\AdminEditorialController;
use App\Http\Controllers\Workspace\AdminPostTermController;
use App\Http\Controllers\Workspace\AdminEditorialReviewController;
use App\Http\Controllers\Workspace\DeveloperController;
use App\Http\Controllers\Workspace\DeveloperTokenController;
use App\Http\Controllers\Workspace\NotificationController;
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
use App\Http\Controllers\Api\McpOAuthDiscoveryController;

// The Passport authorization flow is isolated under /mcp/oauth and
// remains behind hard-disabled Phase 2B acceptance gates.
Route::middleware(['mcp.oauth.stage', 'throttle:20,1'])->prefix('mcp/oauth')->group(function (): void {
    Route::get('/authorize', [\App\Http\Controllers\Api\McpOAuthAuthorizationController::class, 'authorize'])
        ->middleware(['auth', 'password.confirm', 'mcp.oauth.admin', 'mcp.oauth.params:authorize'])
        ->name('mcp.oauth.authorize');
    Route::post('/authorize', [\Laravel\Passport\Http\Controllers\ApproveAuthorizationController::class, 'approve'])
        ->middleware(['auth', 'password.confirm', 'mcp.oauth.admin'])
        ->name('passport.authorizations.approve');
    Route::delete('/authorize', [\Laravel\Passport\Http\Controllers\DenyAuthorizationController::class, 'deny'])
        ->middleware(['auth', 'password.confirm', 'mcp.oauth.admin'])
        ->name('passport.authorizations.deny');
});

Route::middleware(['auth', 'password.confirm', 'mcp.oauth.stage', 'mcp.oauth.admin'])
    ->prefix('dashboard/system-settings/mcp/oauth')->group(function (): void {
        Route::get('/tokens', [\App\Http\Controllers\Workspace\McpOAuthTokenManagementController::class, 'index'])
            ->name('mcp.oauth.tokens.index');
        Route::delete('/tokens/{token}', [\App\Http\Controllers\Workspace\McpOAuthTokenManagementController::class, 'revoke'])
            ->middleware('throttle:10,1')->name('mcp.oauth.tokens.revoke');
    });

// OAuth 2.1 discovery is fail-closed until a real authorization server
// passes security acceptance. The existing /api/mcp bearer route is untouched.
Route::get('/.well-known/oauth-protected-resource', [McpOAuthDiscoveryController::class, 'protectedResource'])
    ->middleware('throttle:30,1')->name('mcp.oauth.metadata.resource');
// RFC 9728 path-aware discovery alias; identical gate and metadata.
Route::get('/.well-known/oauth-protected-resource/api/mcp/oauth', [McpOAuthDiscoveryController::class, 'protectedResource'])
    ->middleware('throttle:30,1')->name('mcp.oauth.metadata.resource.path');
Route::get('/.well-known/oauth-authorization-server', [McpOAuthDiscoveryController::class, 'authorizationServer'])
    ->middleware('throttle:30,1')->name('mcp.oauth.metadata.authorization-server');

Route::get('/install', [InstallController::class, 'index'])->name('install.index');
Route::post('/install/unlock', [InstallController::class, 'unlock'])
    ->middleware('throttle:5,1')->name('install.unlock');
Route::post('/install/complete', [InstallController::class, 'complete'])
    ->middleware('throttle:3,1')->name('install.complete');

Route::get('/', [CatalogController::class, 'home'])->name('home');
Route::get('/brand/assets/{slot}/{version}', [BrandAssetController::class, 'image'])
    ->where('version', '[0-9A-HJKMNP-TV-Z]{26}')->name('brand.asset');
Route::get('/brand/platform.webmanifest', [BrandAssetController::class, 'manifest'])->name('brand.manifest');
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
Route::get('/assessments/exams', [AssessmentAccessController::class, 'catalog'])->name('assessments.exams');
Route::get('/assessments/quizzes', [AssessmentAccessController::class, 'catalog'])->name('assessments.quizzes');
Route::get('/assessments/{exam}', [AssessmentAccessController::class, 'show'])->name('assessments.show');
Route::get('/exams/{exam}', [AssessmentAccessController::class, 'show'])->where('exam', '[1-9][0-9]{5}-[a-z0-9][a-z0-9-]*')->defaults('kind', 'exam')->name('public.exams.show');
Route::get('/quizzes/{exam}', [AssessmentAccessController::class, 'show'])->where('exam', '[1-9][0-9]{5}-[a-z0-9][a-z0-9-]*')->defaults('kind', 'quiz')->name('public.quizzes.show');
Route::get('/recommendations', [LearningHubController::class,'recommendations'])->name('learning.recommendations');
Route::get('/leaderboard', [LearningHubController::class,'leaderboard'])->name('learning.leaderboard');
Route::get('/course-watch', [LearningHubController::class,'watch'])->name('learning.watch');
Route::get('/upload-studio', [LearningHubController::class,'studio'])->name('learning.studio');
Route::get('/authors', [PublicAuthorController::class, 'index'])->name('authors.index');
Route::get('/authors/{profile}', [PublicAuthorController::class, 'show'])->name('authors.show');
Route::get('/editor-media/{media}', [EditorMediaController::class,'show'])->name('editor-media.show');
Route::get('/help-center', [PublicHelpController::class,'index'])->name('help.index');
Route::get('/help-center/{kind}/{entry}', [PublicHelpController::class,'show'])->name('help.show');
Route::get('/verify-certificate', CertificateVerificationController::class)
    ->middleware('throttle:30,1,certificate-verification')->name('certificates.verify');
Route::get('/articles', [PublicArticleController::class, 'index'])->name('articles.index');
Route::get('/articles/{article}', [PublicArticleController::class, 'show'])->name('articles.show');
Route::get('/categories', [CatalogController::class, 'index'])->name('categories.index');
Route::get('/categories/{category}', [CatalogController::class, 'show'])->name('categories.show');
Route::get('/cefr', [CefrController::class, 'index'])->name('cefr.index');
Route::get('/pages/{slug}', [PublicDocumentController::class, 'page'])->name('pages.show');
Route::get('/policies', [PublicDocumentController::class, 'policies'])->name('policies.index');
Route::get('/policies/{slug}', [PublicDocumentController::class, 'policy'])->name('policies.show');
Route::get('/information/{page}', [InformationController::class, 'show'])->name('information.show');
Route::post('/contact', [PublicContactController::class, 'store'])
    ->middleware('throttle:5,1')->name('contact.store');
Route::post('/language', [LocaleController::class, 'update'])->name('language.update');

Route::get('/sign-in/google/callback', [GoogleSignInController::class, 'callback'])
    ->middleware('throttle:20,1')->name('social.google.callback');

Route::middleware('guest')->group(function (): void {
    Route::get('/sign-in/google', [GoogleSignInController::class, 'start'])->middleware('throttle:10,1')->name('social.google.start');
    Route::get('/sign-in/google/register', [GoogleSignInController::class, 'registration'])->name('social.google.registration');
    Route::post('/sign-in/google/register', [GoogleSignInController::class, 'finishRegistration'])->middleware('throttle:5,1')->name('social.google.register');
    Route::get('/security/challenge', [AccountSecurityController::class, 'challenge'])->name('security.challenge');
    Route::post('/security/challenge', [AccountSecurityController::class, 'finishChallenge'])
        ->middleware('throttle:10,1')->block(10, 10)->name('security.challenge.finish');
    Route::get('/passkeys/login/options', [PasskeyLoginController::class, 'index'])
        ->middleware('throttle:10,1')->block(10, 10)->name('passkey.login-options');
    Route::post('/passkeys/login', [AccountPasskeyController::class, 'store'])
        ->middleware('throttle:10,1')->block(10, 10)->name('passkey.login');
    Route::get('/login', [SessionController::class, 'loginForm'])->name('login');
    Route::post('/login', [SessionController::class, 'login'])->name('login.store');
    Route::get('/register', [SessionController::class, 'registerForm'])->name('register');
    Route::post('/register', [SessionController::class, 'register'])->middleware('throttle:5,1')->name('register.store');
});

// Password reset links are token-protected, throttled, and never disclose whether
// an email address exists. SMTP delivery is disabled in the initial log-only install.
Route::middleware('guest')->group(function (): void {
    Route::get('/forgot-password', [PasswordRecoveryController::class, 'requestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordRecoveryController::class, 'sendLink'])
        ->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordRecoveryController::class, 'resetForm'])
        ->name('password.reset');
    Route::post('/reset-password', [PasswordRecoveryController::class, 'reset'])
        ->middleware('throttle:5,1')->name('password.update');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/security/methods', [AccountSecurityController::class, 'methods'])->name('security.methods');
    Route::get('/security/confirm-password', [AccountSecurityController::class, 'passwordForm'])->name('password.confirm');
    Route::post('/security/confirm-password', [AccountSecurityController::class, 'confirmPassword'])
        ->middleware('throttle:5,1')->name('security.password.confirm');

    Route::middleware(['password.confirm', 'throttle:10,1'])->group(function (): void {
        Route::post('/security/two-factor', [AccountSecurityController::class, 'enable'])->name('security.factor.enable');
        Route::post('/security/two-factor/confirm', [AccountSecurityController::class, 'confirm'])->name('security.factor.confirm');
        Route::post('/security/two-factor/recovery-codes', [AccountSecurityController::class, 'regenerate'])->name('security.factor.recovery');
        Route::delete('/security/two-factor', [AccountSecurityController::class, 'disable'])->name('security.factor.disable');
        Route::get('/user/passkeys/options', [PasskeyRegistrationController::class, 'index'])->block(10, 10)->name('passkey.registration-options');
        Route::post('/user/passkeys', [PasskeyRegistrationController::class, 'store'])->block(10, 10)->name('passkey.store');
        Route::delete('/user/passkeys/{passkey}', [PasskeyRegistrationController::class, 'destroy'])->name('passkey.destroy');
        Route::post('/security/google', [GoogleSignInController::class, 'connect'])->name('social.google.connect');
        Route::delete('/security/google', [GoogleSignInController::class, 'disconnect'])->name('social.google.disconnect');
    });

    Route::get('/account-avatar', [ProfileController::class, 'avatar'])->name('workspace.avatar');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('workspace.notifications');
    Route::get('/notifications/unread', [NotificationController::class, 'unread'])
        ->middleware('throttle:30,1,unread-notifications')->name('workspace.notifications.unread');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('workspace.notifications.read-all');
    Route::post('/notifications/{id}/open', [NotificationController::class, 'open'])->whereUuid('id')->name('workspace.notifications.open');

    Route::post('/editor-media', [EditorMediaController::class,'store'])
        ->middleware('throttle:12,1')->name('editor-media.store');
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:3,1')->name('verification.send');

    Route::prefix('support')->name('support.')->group(function (): void {
        Route::get('/tickets', [SupportTicketController::class,'index'])->name('tickets.index');
        Route::get('/tickets/create', [SupportTicketController::class,'create'])->name('tickets.create');
        Route::post('/tickets', [SupportTicketController::class,'store'])->middleware('throttle:6,1')->name('tickets.store');
        Route::post('/chat/start', [SupportTicketController::class,'startChat'])->middleware('throttle:5,1')->name('chat.start');
        Route::get('/tickets/{ticket}', [SupportTicketController::class,'show'])->name('tickets.show');
        Route::patch('/tickets/{ticket}', [SupportTicketController::class,'update'])->name('tickets.update');
        Route::delete('/tickets/{ticket}', [SupportTicketController::class,'destroy'])->name('tickets.destroy');
        Route::get('/tickets/{ticket}/messages', [SupportTicketController::class,'messages'])->middleware('throttle:120,1')->name('messages.index');
        Route::post('/tickets/{ticket}/messages', [SupportTicketController::class,'reply'])->middleware('throttle:30,1')->name('messages.store');
    });

    Route::get('/assessment-attempts/{attempt}/recording/{question}', [StudentExaminationController::class, 'recording'])
        ->whereNumber('question')->name('assessments.recording');

    Route::get('/certificates/{certificate:code}', [\App\Http\Controllers\Workspace\CertificateController::class, 'show'])->name('certificates.show');
    Route::get('/certificates/{certificate:code}/background', [\App\Http\Controllers\Workspace\CertificateController::class, 'issuedBackground'])->name('certificates.background');

    Route::post('/logout', [SessionController::class, 'logout'])->name('logout');

    Route::middleware('role:admin')->prefix('dashboard')->name('admin.')->group(function (): void {
        Route::prefix('system-settings')->name('system-settings.')->controller(AdminSystemSettingsController::class)->group(function (): void {
            Route::get('/branding', [AdminBrandingController::class, 'edit'])->name('branding');
            Route::put('/branding', [AdminBrandingController::class, 'update'])->name('branding.update');
            Route::get('/security', 'security')->name('security');
            Route::get('/social-auth', 'socialAuth')->name('social-auth');
            Route::get('/mcp', [\App\Http\Controllers\Workspace\AdminMcpManagementController::class, 'index'])->name('mcp');
            Route::post('/mcp/sessions', [\App\Http\Controllers\Workspace\McpAdminSessionController::class, 'issue'])
                ->middleware(['password.confirm','throttle:5,1'])->name('mcp.sessions.issue');
            Route::post('/mcp/confirm-password', [\App\Http\Controllers\Workspace\McpAdminSessionController::class, 'confirmWebsitePassword'])
                ->middleware('throttle:5,1')->name('mcp.confirm-password');
            Route::delete('/mcp/sessions/{token}', [\App\Http\Controllers\Workspace\McpAdminSessionController::class, 'revoke'])
                ->whereNumber('token')->middleware('throttle:10,1')->name('mcp.sessions.revoke');
            Route::get('/mcp/approvals/{approval}', [\App\Http\Controllers\Workspace\McpBrowserApprovalController::class, 'show'])
                ->middleware('password.confirm')->name('mcp.approvals.show');
            Route::post('/mcp/approvals/{approval}', [\App\Http\Controllers\Workspace\McpBrowserApprovalController::class, 'decide'])
                ->middleware(['password.confirm','throttle:5,1'])->name('mcp.approvals.decide');
            Route::get('/cloud-storage', 'cloudStorage')->name('cloud-storage');
            Route::get('/cloud-storage/configure', 'configureCloudStorage')
                ->middleware('password.confirm')->name('cloud-storage.configure');
            Route::patch('/cloud-storage/configure', 'saveCloudStorage')
                ->middleware(['password.confirm', 'throttle:6,1'])->name('cloud-storage.save');
            Route::post('/cloud-storage/probe', 'probeCloudStorage')
                ->middleware('throttle:3,1')->name('cloud-storage.probe');
            Route::post('/cloud-storage/transfer', 'transferCloudStorage')
                ->middleware(['password.confirm', 'throttle:5,1'])->name('cloud-storage.transfer');
        });
        Route::get('/', [WorkspaceController::class, 'admin'])->name('overview');
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::patch('/profile/password', [ProfileController::class, 'password'])->middleware('throttle:5,1')->name('profile.password');
        foreach (['exams' => 'exams', 'quiz-manager' => 'quizzes-manager'] as $path => $name) {
            Route::get("/$path", [AdminExaminationController::class, 'index'])->name("$name.index");
            Route::get("/$path/create", [AdminExaminationController::class, 'create'])->name("$name.create");
            Route::post("/$path", [AdminExaminationController::class, 'save'])->name("$name.store");
            Route::get("/$path/{exam}/edit", [AdminExaminationController::class, 'edit'])->name("$name.edit");
            Route::patch("/$path/{exam}", [AdminExaminationController::class, 'save'])->name("$name.update");
            Route::patch("/$path/{exam}/status", [AdminLearningRecordController::class, 'assessmentStatus'])->name("$name.status");
            Route::delete("/$path/{exam}", [AdminLearningRecordController::class, 'deleteAssessment'])->name("$name.destroy");
            Route::get("/$path/{exam}/preview", [AdminLearningRecordController::class, 'assessmentPreview'])->name("$name.preview");
            Route::get("/$path/{exam}/assign", [AdminLearningRecordController::class, 'assessmentUsers'])->name("$name.users");
            Route::post("/$path/{exam}/assign", [AdminLearningRecordController::class, 'assignAssessment'])->name("$name.assign");
        }
        Route::patch('/assessment-access/{access}', [AssessmentAccessController::class, 'review'])->name('assessment-access.review');
        Route::patch('/assessment-attempts/{attempt}/grade', [AdminExaminationController::class, 'grade'])->name('assessment-attempts.grade');
        foreach (['exam-types', 'quiz-types', 'certificate-types'] as $type) {
            Route::get("/$type", [\App\Http\Controllers\Workspace\AdminExaminationController::class, 'types'])->defaults('kind', $type)->name("$type.index");
            Route::post("/$type", [\App\Http\Controllers\Workspace\AdminExaminationController::class, 'saveType'])->defaults('kind', $type)->name("$type.store");
            Route::patch("/$type/{id}", [\App\Http\Controllers\Workspace\AdminExaminationController::class, 'updateType'])->whereNumber('id')->defaults('kind', $type)->name("$type.update");
        }
        Route::get('/certificate-templates', [\App\Http\Controllers\Workspace\CertificateController::class, 'templates'])->name('certificate-templates.index');
        Route::get('/certificate-templates/create', [\App\Http\Controllers\Workspace\CertificateController::class, 'builder'])->name('certificate-templates.create');
        Route::post('/certificate-templates', [\App\Http\Controllers\Workspace\CertificateController::class, 'save'])->name('certificate-templates.store');
        Route::get('/certificate-templates/{template}/edit', [\App\Http\Controllers\Workspace\CertificateController::class, 'builder'])->name('certificate-templates.edit');
        Route::get('/certificate-templates/{template}/background', [\App\Http\Controllers\Workspace\CertificateController::class, 'templateBackground'])->name('certificate-templates.background');
        Route::patch('/certificate-templates/{template}', [\App\Http\Controllers\Workspace\CertificateController::class, 'save'])->name('certificate-templates.update');
        Route::get('/certificates', [\App\Http\Controllers\Workspace\CertificateController::class, 'index'])->name('certificates.index');
        Route::post('/enrollments/{enrollment}/certificates', [\App\Http\Controllers\Workspace\CertificateController::class, 'issue'])->name('certificates.issue');
        Route::patch('/certificates/{certificate}/revoke', [\App\Http\Controllers\Workspace\CertificateController::class, 'revoke'])->name('certificates.revoke');
        Route::get('/media-library', [EditorMediaController::class, 'index'])->name('media-library.index');
        Route::post('/media-library', [EditorMediaController::class, 'uploadToLibrary'])
            ->middleware('throttle:12,1')->name('media-library.store');
        Route::patch('/media-library/{media}', [EditorMediaController::class, 'updateLibrary'])
            ->middleware('throttle:12,1')->name('media-library.update');
        Route::delete('/media-library/{media}', [EditorMediaController::class, 'destroyLibrary'])
            ->name('media-library.destroy');
        Route::get('/policy-history/{entry}', [AdminEditorialController::class, 'policyHistory'])
            ->name('policy-history.index');
        Route::get('/policy-history/{entry}/{revision}', [AdminEditorialController::class, 'policyRevision'])
            ->name('policy-history.show');
        Route::get('/users', [AdminRecordsController::class, 'users'])->name('users.index');
        Route::get('/users/{user}', [AdminRecordsController::class, 'user'])->where('user', '[1-9][0-9]{7}')->name('users.show');
        Route::get('/support/{kind}', [SupportTicketController::class,'adminIndex'])->name('support.index');
        Route::patch('/support/{kind}/{ticket}', [SupportTicketController::class,'adminUpdate'])->name('support.update');
        Route::delete('/support/{kind}/{ticket}', [SupportTicketController::class,'adminDestroy'])->name('support.destroy');
        Route::get('/contact-inquiries', [AdminContactInquiryController::class, 'index'])->name('contact-inquiries.index');
        Route::patch('/contact-inquiries/{inquiry}', [AdminContactInquiryController::class, 'update'])
            ->middleware('throttle:30,1')->name('contact-inquiries.update');
        Route::delete('/contact-inquiries/{inquiry}', [AdminContactInquiryController::class, 'destroy'])->name('contact-inquiries.destroy');
        Route::get('/courses', [AdminCourseController::class, 'index'])->name('courses.index');
        Route::patch('/courses/{course}/status', [AdminLearningRecordController::class, 'courseStatus'])->name('courses.status');
        Route::delete('/courses/{course}', [AdminLearningRecordController::class, 'deleteCourse'])->name('courses.destroy');
        Route::get('/courses/{course}/preview', [AdminLearningRecordController::class, 'coursePreview'])->name('courses.preview');
        Route::get('/courses/{course}/assign', [AdminLearningRecordController::class, 'courseUsers'])->name('courses.users');
        Route::post('/courses/{course}/assign', [AdminLearningRecordController::class, 'assignCourse'])->name('courses.assign');
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
            Route::patch('/{course}/media', [AdminCourseController::class, 'media'])->name('media');
            Route::post('/{course}/submit', [AdminCourseController::class, 'submit'])->name('submit');
            Route::get('/{course}/history', [AdminCurriculumHistoryController::class, 'index'])->name('history.index');
            Route::post('/{course}/modules/{module}/duplicate', [AdminCurriculumController::class, 'duplicateModule'])->name('modules.duplicate');
            Route::post('/{course}/modules/{module}/lessons/{lesson}/duplicate', [AdminCurriculumController::class, 'duplicateLesson'])->name('lessons.duplicate');
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
            Route::patch('/{course}/module-order', [AdminCurriculumController::class, 'reorderModules'])->name('modules.reorder');
            Route::patch('/{course}/modules/{module}/lesson-order', [AdminCurriculumController::class, 'reorderLessons'])->name('lessons.reorder');
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
            Route::get('/slug', [AdminEditorialController::class, 'slug'])->name('slug');
            Route::post('/', [AdminEditorialController::class, 'store'])->name('store');
            Route::get('/{entry}/edit', [AdminEditorialController::class, 'edit'])->name('edit');
            Route::get('/{entry}/preview', [AdminEditorialController::class, 'preview'])->name('preview');
            Route::patch('/{entry}', [AdminEditorialController::class, 'update'])->name('update');
            Route::delete('/{entry}', [AdminEditorialController::class, 'destroy'])->name('destroy');
        });
        Route::prefix('post-terms/{kind}')->name('post-terms.')->group(function (): void {
            Route::get('/', [AdminPostTermController::class,'index'])->name('index');
            Route::get('/create', [AdminPostTermController::class,'create'])->name('create');
            Route::post('/', [AdminPostTermController::class,'store'])->name('store');
            Route::get('/{term}/edit', [AdminPostTermController::class,'edit'])->name('edit');
            Route::patch('/{term}', [AdminPostTermController::class,'update'])->name('update');
            Route::delete('/{term}', [AdminPostTermController::class,'destroy'])->name('destroy');
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
        Route::get('/assessments', [AssessmentAccessController::class, 'assigned'])->name('assessments.index');
        Route::post('/assessments/{exam}/start', [StudentExaminationController::class, 'startStandalone'])
            ->middleware('throttle:12,1,assessment-start')->name('assessment-attempts.start');
        Route::post('/assessments/{exam}/access', [AssessmentAccessController::class, 'requestAccess'])
            ->middleware('throttle:12,1,assessment-access')->name('assessment-access.request');
        Route::get('/assessment-attempts/{attempt}', [StudentExaminationController::class, 'showStandalone'])->name('assessment-attempts.show');
        Route::post('/assessment-attempts/{attempt}', [StudentExaminationController::class, 'submitStandalone'])
            ->middleware('throttle:12,1,assessment-submit')->name('assessment-attempts.submit');
        Route::post('/assessment-attempts/{attempt}/feedback', [StudentExaminationController::class, 'feedback'])
            ->middleware('throttle:90,1,assessment-feedback')->name('assessment-attempts.feedback');
        Route::post('/enrollments/{enrollment}/exam-attempts/{attempt}/feedback', [StudentExaminationController::class, 'courseFeedback'])
            ->middleware('throttle:90,1')->name('exam-attempts.feedback');
        Route::post('/enrollments/{enrollment}/exams/{exam}', [\App\Http\Controllers\Workspace\StudentExaminationController::class, 'start'])
            ->middleware('throttle:12,1')->name('exam-attempts.start');
        Route::get('/enrollments/{enrollment}/exam-attempts/{attempt}', [\App\Http\Controllers\Workspace\StudentExaminationController::class, 'show'])->name('exam-attempts.show');
        Route::post('/enrollments/{enrollment}/exam-attempts/{attempt}', [\App\Http\Controllers\Workspace\StudentExaminationController::class, 'submit'])
            ->middleware('throttle:12,1')->name('exam-attempts.submit');
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
        Route::post('/enrollments/{enrollment}/lessons/{lesson}/quiz', [StudentLessonQuizController::class, 'store'])
            ->middleware('throttle:20,1')->name('lessons.quiz');
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
