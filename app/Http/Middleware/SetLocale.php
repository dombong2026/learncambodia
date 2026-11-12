<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale', config('learning.default_locale', 'en'));

        if (! array_key_exists($locale, config('learning.supported_locales', []))) {
            $locale = 'en';
        }

        App::setLocale($locale);

        return $next($request);
    }
}
