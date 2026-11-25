<?php

namespace App\Providers;

use App\Models\Category;
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
        View::composer('partials.header', function (ViewInstance $view): void {
            $view->with(
                'navigationCategories',
                Category::forNavigation()->get()
            );
        });
    }
}
