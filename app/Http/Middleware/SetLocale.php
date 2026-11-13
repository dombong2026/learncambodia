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
        $supported = config('learning.supported_locales', []);
        $explicit = $request->query('lang');
        $stored = $request->session()->get('locale');

        // URL language has priority, then a previously chosen language, then
        // the browser's Accept-Language preference (first visit only).
        if (is_string($explicit) && array_key_exists($explicit, $supported)) {
            $locale = $explicit;
            $request->session()->put('locale', $locale);
        } elseif (is_string($stored) && array_key_exists($stored, $supported)) {
            $locale = $stored;
        } else {
            $preferred = $request->getPreferredLanguage(array_keys($supported));
            $locale = is_string($preferred) && array_key_exists($preferred, $supported)
                ? $preferred : config('learning.default_locale', 'en');
        }

        App::setLocale($locale);

        return $next($request);
    }
}
