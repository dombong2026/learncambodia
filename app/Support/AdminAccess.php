<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

final class AdminAccess
{
    public const ROLES = [
        User::ROLE_STUDENT => 'Student',
        User::ROLE_INSTRUCTOR => 'Instructor',
        User::ROLE_DEVELOPER => 'Developer',
        User::ROLE_ADMIN => 'Admin',
        User::ROLE_SUPERADMIN => 'Super Admin',
    ];

    public const PERMISSIONS = [
        'users' => 'Users and account access',
        'learning' => 'Courses, instructors, exams, quizzes, certificates and levels',
        'editorial' => 'Pages, posts, policies and help content',
        'media' => 'Media library',
        'appearance' => 'Site appearance',
        'support' => 'Support tickets, live chat and contact inquiries',
        'reviews' => 'Reviews, enrollments, gradebook and academic evidence',
        'reports' => 'Learning reports',
        'settings' => 'System settings',
    ];

    private const ROUTE_PREFIXES = [
        'users' => ['admin.users.'],
        'learning' => [
            'admin.courses.', 'admin.course-builder.', 'admin.instructors.', 'admin.cefr.',
            'admin.categories.', 'admin.exams.', 'admin.quizzes-manager.', 'admin.quizzes.',
            'admin.exam-types.', 'admin.quiz-types.', 'admin.certificate-types.', 'admin.certificate-templates.',
            'admin.certificates.', 'admin.assessment-access.', 'admin.assessment-attempts.',
        ],
        'editorial' => ['admin.editorial.', 'admin.post-terms.', 'admin.policy-history.'],
        'media' => ['admin.media-library.'],
        'appearance' => ['admin.appearance.'],
        'support' => ['admin.support.', 'admin.contact-inquiries.'],
        'reviews' => ['admin.reviews.', 'admin.academic.', 'admin.homework.', 'admin.public-profiles.'],
        'reports' => ['admin.learning.report'],
        'settings' => ['admin.system-settings.'],
    ];

    public static function grants(User $user): array
    {
        if (! $user->isAdministrator()) {
            return [];
        }

        return self::unrestricted($user)
            ? array_keys(self::PERMISSIONS)
            : array_values(array_intersect(array_keys(self::PERMISSIONS), $user->admin_permissions ?? []));
    }

    public static function unrestricted(User $user): bool
    {
        return $user->role === User::ROLE_SUPERADMIN
            || ($user->role === User::ROLE_ADMIN && $user->admin_permissions === null);
    }

    public static function can(?User $user, string $permission): bool
    {
        return $user !== null && in_array($permission, self::grants($user), true);
    }

    public static function allowsRoute(User $user, string $route): bool
    {
        if (self::unrestricted($user) || $route === 'admin.overview' || str_starts_with($route, 'admin.profile.')) {
            return $user->isAdministrator();
        }

        foreach (self::ROUTE_PREFIXES as $permission => $prefixes) {
            if (Str::startsWith($route, $prefixes)) {
                return self::can($user, $permission);
            }
        }

        return false;
    }

    public static function assignableRoles(User $actor): array
    {
        return $actor->role === User::ROLE_SUPERADMIN
            ? self::ROLES
            : array_diff_key(self::ROLES, [User::ROLE_SUPERADMIN => true]);
    }

    public static function canEdit(User $actor, User $target): bool
    {
        return self::can($actor, 'users') && ! $actor->is($target)
            && ($actor->role === User::ROLE_SUPERADMIN
                || ($target->role !== User::ROLE_SUPERADMIN && array_diff(self::grants($target), self::grants($actor)) === []));
    }
}
