<?php

namespace App\Http\Controllers\Api;

use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Passport\Client;
use Laravel\Passport\Http\Controllers\AuthorizationController;

/**
 * Always show administrator consent. Passport normally reuses an earlier
 * permission grant silently, but this MCP client must not auto-approve it.
 */
final class McpOAuthAuthorizationController extends AuthorizationController
{
    protected function hasGrantedScopes(Authenticatable $user, Client $client, array $scopes): bool
    {
        return false;
    }
}
