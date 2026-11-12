<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_STUDENT = 'student';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_DEVELOPER = 'developer';
    public const ROLE_INSTRUCTOR = 'instructor';

    // Match the database default for models created in the current request.
    protected $attributes = ['role' => self::ROLE_STUDENT];

    public function homeRouteName(): string
    {
        return match ($this->role) {
            self::ROLE_ADMIN => 'admin.overview',
            self::ROLE_DEVELOPER => 'developer.overview',
            self::ROLE_INSTRUCTOR => 'instructor.overview',
            default => 'account.overview',
        };
    }

    public function isAdministrator(): bool
    {
        return $this->role === self::ROLE_ADMIN;
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
            'terms_accepted_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
