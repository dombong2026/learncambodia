<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(array_keys(config('learning.supported_locales')))],
        ]);

        $locale = $validated['locale'];
        $request->session()->put('locale', $locale);

        // Keep the user on the SAME public/account page. Only accept an
        // origin-matching referer; never redirect to an arbitrary external URL.
        $previous = (string) $request->headers->get('referer', '');
        $parts = parse_url($previous);
        $expected = parse_url($request->getSchemeAndHttpHost());

        // The live request host may differ from APP_URL in local QA and
        // behind a reverse proxy. Compare hosts, then redirect by PATH only.
        if (! is_array($parts) || ! is_array($expected)
            || ($parts['host'] ?? '') !== ($expected['host'] ?? '')
            || ($parts['port'] ?? null) !== ($expected['port'] ?? null)) {
            $previous = route($request->user()?->homeRouteName() ?? 'home');
            $parts = parse_url($previous);
        }

        $path = $parts['path'] ?? '/';
        if (! is_string($path) || ! str_starts_with($path, '/') ||
            str_starts_with($path, '//') || str_contains($path, '\\') ||
            preg_match('/[\x00-\x1F\x7F]/', $path)) {
            $path = '/';
        }
        parse_str($parts['query'] ?? '', $query);
        // Prevent a query parameter from creating an open redirect or
        // overriding the validated locale.
        $query['lang'] = $locale;

        return redirect()->to($path.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986));
    }
}
