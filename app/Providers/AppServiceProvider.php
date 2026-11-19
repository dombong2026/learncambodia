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
