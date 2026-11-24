<?php

namespace App\Models;

use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens as HasPassportApiTokens;

/**
 * Passport-only view of the existing users table.
 *
 * The ordinary User model deliberately retains Sanctum's HasApiTokens trait,
 * including its createToken(), tokens() and currentAccessToken() methods.
 * The mcp_oauth_users provider alone hydrates this Passport model.
 */
final class McpOAuthUser extends User implements OAuthenticatable
{
    use HasPassportApiTokens;
}
