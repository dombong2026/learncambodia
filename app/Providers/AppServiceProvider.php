<?php

namespace App\Providers;

use App\Models\Category;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;
use Laravel\Fortify\Fortify;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Fortify::ignoreRoutes();
    }

    public function boot(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            if ($event->guard === 'web' && request()->hasSession()) {
                request()->session()->put('account_auth_version', (int) $event->user->account_auth_version);
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
