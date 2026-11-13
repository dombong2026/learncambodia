<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser security response headers only. No changes to public page layouts.
 *
 * CSP is intentionally deferred until every third-party embed and Vite asset
 * is inventoried; a guessed enforced CSP could break legitimate lessons.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        // Enable HSTS only on actual HTTPS responses from the production app.
        // A short initial age prevents premature subdomain-wide commitment.
        if ($request->isSecure() && app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=86400');
        }

        // Protect accounts, protected learning, authentication forms, and
        // the one-time installer against intermediary caching.
        if ($request->user() !== null
            || $request->is('install', 'install/*', 'login', 'register',
                'dashboard', 'dashboard/*', 'account', 'account/*',
                'instructor', 'instructor/*', 'developer', 'developer/*')) {
            $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        }

        return $response;
    }
}
