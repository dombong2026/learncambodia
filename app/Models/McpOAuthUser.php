<?php

namespace App\Models;

use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailImplementation;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens as HasPassportApiTokens;

/**
 * A separate Passport authentication model referencing the existing users
 * database table. The regular User model and all its Sanctum API tokens are
 * intentionally untouched.
 *
 * Never extend User here: Sanctum and Passport both declare the incompatible
 * $accessToken property, so mixing both HasApiTokens traits breaks PHP.
 */
final class McpOAuthUser extends Authenticatable implements OAuthenticatable, MustVerifyEmail
{
    use HasPassportApiTokens, MustVerifyEmailImplementation;

    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret',
        'two_factor_recovery_codes', 'google_subject', 'profile_details'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime'];
    }

    public function isAdministrator(): bool
    {
        return in_array($this->role, [User::ROLE_ADMIN, User::ROLE_SUPERADMIN], true);
    }
}
