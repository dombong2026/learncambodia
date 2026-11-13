<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureDeveloperToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        abort_unless(
            $user?->isDeveloper()
                && $token instanceof PersonalAccessToken
                && $token->can('catalog:read')
                && ($token->expires_at === null || $token->expires_at->isFuture()),
            403
        );

        return $next($request);
    }
}
