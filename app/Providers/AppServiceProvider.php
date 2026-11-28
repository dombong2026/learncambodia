<?php

namespace App\Providers;

use App\Models\Category;
use App\Support\ManagedSmtpSettings;
use App\Support\SpecializedExamAttempts;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Verified;
use Illuminate\Mail\MailManager;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;
use Laravel\Fortify\Fortify;
use Laravel\Passport\Passport;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Api\McpOAuthAuthorizationController;
use Carbon\CarbonInterval;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Fortify::ignoreRoutes();
        // The OAuth code/token routes are registered explicitly under the
        // staging gate; do not let Passport silently expose /oauth/* routes.
        if (class_exists(Passport::class)) {
            Passport::ignoreRoutes();
            $this->app->when(McpOAuthAuthorizationController::class)
                ->needs(StatefulGuard::class)
                ->give(fn () => Auth::guard('web'));
        }
        $this->app->resolving(MailManager::class, ManagedSmtpSettings::apply(...));
    }

    public function boot(): void
    {
        if (class_exists(Passport::class)) {
            // Disabled OAuth routes must close before Passport resolves signing keys.
            $this->app->make(\Illuminate\Contracts\Http\Kernel::class)
                ->prependToMiddlewarePriority(\App\Http\Middleware\EnsureMcpOAuthApiEnabled::class);
            Passport::tokensCan(\App\Support\McpOAuthDraftScopePolicy::scopes());
            Passport::defaultScopes([]);
            Passport::tokensExpireIn(CarbonInterval::minutes(20));
            Passport::refreshTokensExpireIn(CarbonInterval::days(7));
            Passport::$revokeRefreshTokenAfterUse = true;
            Passport::$implicitGrantEnabled = false;
            Passport::$passwordGrantEnabled = false;
            Passport::$deviceCodeGrantEnabled = false;
            Passport::authorizationView('workspace.admin.mcp-oauth-consent');
        }
        Event::listen(Login::class, function (Login $event): void {
            if ($event->guard === 'web' && request()->hasSession()) {
                request()->session()->put('account_auth_version', (int) $event->user->account_auth_version);
                if ($event->user instanceof User) {
                    SpecializedExamAttempts::afterLogin(request(), $event->user);
                }
            }
        });

        Event::listen(Verified::class, static function (Verified $event): void {
            if ($event->user instanceof User) {
                SpecializedExamAttempts::claimVerifiedEmail($event->user);
            }
        });

        Event::listen(JobProcessing::class, static function (): void {
            if (app()->resolved('mail.manager')) {
                ManagedSmtpSettings::refresh();
            }
        });

        View::composer('partials.header', function (ViewInstance $view): void {
            $view->with(
                'navigationCategories',
                Category::forNavigation()->get()
            );
        });
    }
}
