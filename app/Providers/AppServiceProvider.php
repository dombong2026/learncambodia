<?php

namespace App\Providers;

use App\Models\Category;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;
use Laravel\Fortify\Fortify;
use Laravel\Passport\Passport;
use Carbon\CarbonInterval;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Fortify::ignoreRoutes();
    }

    public function boot(): void
    {
        View::composer('partials.header', function (ViewInstance $view): void {
            $view->with(
                'navigationCategories',
                Category::forNavigation()->get()
            );
        });
    }
}
