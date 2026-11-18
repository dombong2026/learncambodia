<?php

namespace App\Providers;

use App\Models\Category;
use App\Support\ManagedSmtpSettings;
use Illuminate\Auth\Events\Login;
use Illuminate\Mail\MailManager;
use Illuminate\Queue\Events\JobProcessing;
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
        $this->app->resolving(MailManager::class, ManagedSmtpSettings::apply(...));
    }

    public function boot(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            if ($event->guard === 'web' && request()->hasSession()) {
                request()->session()->put('account_auth_version', (int) $event->user->account_auth_version);
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
