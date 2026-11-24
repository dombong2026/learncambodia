<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureMcpOAuthStagingEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        // Two permanently false release gates keep the transport dark until a
        // separate audited promotion explicitly changes config/mcp.php.
        abort_unless(config('mcp.gateway_enabled', false)
            && config('mcp.oauth_provider_staging_enabled', false)
            && config('mcp.oauth_provider_accepted', false)
            && config('mcp.oauth_ready', false), 404, 'MCP OAuth authorization is not yet available.');

        return $next($request);
    }
}
