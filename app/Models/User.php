<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'profile_details', 'avatar_path', 'two_factor_secret', 'two_factor_recovery_codes', 'google_subject', 'admin_permissions', 'phone', 'phone_verified_at', 'account_auth_version'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasPublicId, Notifiable;

    use PasskeyAuthenticatable, TwoFactorAuthenticatable;

    public const ROLE_STUDENT = 'student';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_SUPERADMIN = 'superadmin';
    public const ROLE_DEVELOPER = 'developer';
    public const ROLE_INSTRUCTOR = 'instructor';

    // Match the database default for models created in the current request.
    protected $attributes = ['role' => self::ROLE_STUDENT, 'account_status' => 'active', 'account_auth_version' => 0];

    private function publicIdDigits(): int
    {
        return 8;
    }

    public function getRouteKey()
    {
        return $this->public_id;
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return $query->where($field ?? 'public_id', $value);
    }

    public function homeRouteName(): string
    {
        return match ($this->role) {
            self::ROLE_ADMIN, self::ROLE_SUPERADMIN => 'admin.overview',
            self::ROLE_DEVELOPER => 'developer.overview',
            self::ROLE_INSTRUCTOR => 'instructor.overview',
            default => 'account.overview',
        };
    }

    public function isActiveAccount(): bool
    {
        return $this->account_status === 'active';
    }

    public function isAdministrator(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_SUPERADMIN], true);
    }

    public function isInstructor(): bool
    {
        return $this->role === self::ROLE_INSTRUCTOR;
    }

    public function isDeveloper(): bool
    {
        return $this->role === self::ROLE_DEVELOPER;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'account_auth_version' => 'integer',
            'two_factor_confirmed_at' => 'datetime',
            'profile_details' => 'encrypted:array',
            'terms_accepted_at' => 'datetime',
            'password' => 'hashed',
            'admin_permissions' => 'array',
        ];
    }
}
