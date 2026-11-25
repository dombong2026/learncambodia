<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMcpOAuthStagingEnabled
{
    public static function productionRedirectIsValid(string $redirect): bool
    {
        // The stable callback requires RFC 9207 support, not advertised here.
        return (bool) preg_match('~\Ahttps://chatgpt\.com/connector/oauth/[A-Za-z0-9_-]{8,128}\z~D', $redirect);
    }

    public static function productionRegistrationAllowed(): bool
    {
        return app()->environment('production')
            && config('mcp.oauth_production_registration_enabled', false)
            && ! config('mcp.oauth_production_accepted', false)
            && ! config('mcp.oauth_production_enabled', false)
            && ! config('mcp.oauth_discovery_enabled', false)
            && ! config('mcp.oauth_provider_staging_enabled', false);
    }

    public static function enabled(): bool
    {
        if (! config('mcp.gateway_enabled', false)) {
            return false;
        }

        if (app()->environment('production')) {
            $client = config('mcp.oauth_client_id');
            $redirect = config('mcp.oauth_redirect_uri');

            return config('mcp.oauth_production_accepted', false)
                && config('mcp.oauth_production_enabled', false)
                && config('mcp.oauth_discovery_enabled', false)
                && ! config('mcp.oauth_production_registration_enabled', false)
                && ! config('mcp.oauth_provider_staging_enabled', false)
                && is_string($client) && (bool) preg_match('/\A[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\z/D', $client)
                && is_string($redirect) && self::productionRedirectIsValid($redirect)
                && config('mcp.oauth_resource') === 'https://learncambodia.com/api/mcp/oauth';
        }

        return app()->environment(['local', 'testing', 'staging'])
            && config('mcp.oauth_provider_staging_enabled', false)
            && config('mcp.oauth_provider_accepted', false)
            && config('mcp.oauth_ready', false);
    }

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(self::enabled(), 404, 'MCP OAuth authorization is not yet available.');

        return $next($request);
    }
}
