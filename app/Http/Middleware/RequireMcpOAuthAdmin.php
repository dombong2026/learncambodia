<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireMcpOAuthAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isAdministrator()
            && $request->user()->hasVerifiedEmail(), 403, 'Verified website administrator required.');
        return $next($request);
    }
}
