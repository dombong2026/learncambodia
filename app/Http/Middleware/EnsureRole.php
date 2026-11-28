<?php

namespace App\Http\Middleware;

use App\Support\AdminAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless($request->user() !== null
            && (in_array($request->user()->role, $roles, true)
                || (in_array('admin', $roles, true) && $request->user()->isAdministrator())), 403);

        $name = $request->route()?->getName() ?? '';
        if (str_starts_with($name, 'admin.')) {
            abort_unless(AdminAccess::allowsRoute($request->user(), $name), 403);
        }

        return $next($request);
    }
}
