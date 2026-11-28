<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class AccountPreview
{
    public const KEY = 'account_preview';

    private const READ_ROUTES = [
        'home', 'workspace.avatar',
        'account.overview', 'account.courses.index', 'account.enrollments.index', 'account.enrollments.show',
        'account.assessments.index', 'account.progress.index', 'account.saved-courses.index',
        'instructor.overview', 'instructor.courses.index', 'instructor.courses.show', 'instructor.insights.index',
        'developer.overview', 'developer.apis', 'developer.usage',
    ];

    public static function allowed(User $actor, User $target): bool
    {
        return AdminAccess::canEdit($actor, $target) && $target->isActiveAccount()
            && ! $target->isAdministrator();
    }

    public static function permits(Request $request): bool
    {
        return $request->routeIs('account-preview.stop')
            || (in_array($request->method(), ['GET', 'HEAD'], true)
                && in_array($request->route()?->getName(), self::READ_ROUTES, true));
    }

    public static function switchAccount(Request $request, ?User $user): void
    {
        Auth::guard('web')->logoutCurrentDevice();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($user) {
            Auth::guard('web')->login($user);
        }
    }

    public static function finish(Request $request, array $preview, ?User $actor, ?User $target): RedirectResponse
    {
        if ($actor && $target) {
            ManagedAccount::audit($actor, $target, 'preview_ended');
        }

        $canReturn = $actor && $actor->isActiveAccount() && AdminAccess::can($actor, 'users')
            && $actor->account_auth_version === $preview['actor_version'];
        self::switchAccount($request, $canReturn ? $actor : null);

        return to_route($canReturn ? 'admin.users.index' : 'login')
            ->with('status', 'Support session ended.');
    }
}
