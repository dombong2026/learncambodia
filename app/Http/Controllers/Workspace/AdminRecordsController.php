<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CefrLevel;
use App\Models\Course;
use App\Models\User;
use App\Support\AdminAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminRecordsController extends Controller
{
    public function users(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', Rule::in(['active', 'invited', 'deactivated', 'deleted'])],
            'verification' => ['nullable', Rule::in(['email_verified', 'email_unverified', 'phone_verified', 'phone_unverified'])],
            'role' => ['nullable', Rule::in(array_keys(AdminAccess::ROLES))],
        ]);
        $query = trim($filters['q'] ?? '');
        $role = $filters['role'] ?? null;
        $status = $filters['status'] ?? null;
        $verification = $filters['verification'] ?? null;

        $users = User::query()->select([
            'id', 'public_id', 'name', 'email', 'role', 'created_at', 'account_status',
            'admin_permissions', 'phone', 'phone_verified_at', 'email_verified_at',
        ])->with('invitation:user_id,expires_at,sent_at,accepted_at,revoked_at')
            ->when($query, fn ($builder) => $builder->where(function ($inner) use ($query) {
                $inner->where('name', 'like', '%'.$query.'%')
                    ->orWhere('email', 'like', '%'.$query.'%')
                    ->when(preg_match('/^[1-9][0-9]{7}$/D', $query), fn ($match) => $match->orWhere('public_id', $query));
            }))
            ->when($status, fn ($builder) => $builder->where('account_status', $status),
                fn ($builder) => $builder->where('account_status', '!=', 'deleted'))
            ->when($role, fn ($builder) => $builder->where('role', $role))
            ->when($verification, function ($builder) use ($verification) {
                match ($verification) {
                    'email_verified' => $builder->whereNotNull('email_verified_at'),
                    'email_unverified' => $builder->whereNull('email_verified_at'),
                    'phone_verified' => $builder->whereNotNull('phone_verified_at'),
                    'phone_unverified' => $builder->whereNotNull('phone')->whereNull('phone_verified_at'),
                };
            })
            ->orderByDesc('id')->paginate(10)->withQueryString();

        return view('workspace.admin.records', [
            'title' => 'User Manager',
            'description' => 'Create accounts and manage access to LearnCambodia.',
            'columns' => ['User ID', 'Name', 'Email', 'Role', 'Status', 'Joined', 'Actions'],
            'userFilters' => ['q' => $query, 'role' => $role, 'status' => $status, 'verification' => $verification],
            'invitationDeliveryEnabled' => \App\Support\AccountInvitations::deliveryEnabled(),
            'records' => $users->through(fn (User $user) => [
                ['value' => $user->public_id, 'url' => route('admin.users.show', $user)],
                ['value' => $user->name, 'url' => route('admin.users.show', $user)],
                ['email' => $user->email, 'verified' => $user->hasVerifiedEmail()],
                AdminAccess::ROLES[$user->role] ?? $user->role,
                $user->isInvitedAccount() ? ($user->invitation?->statusLabel() ?? __('invitations.invited')) : ucfirst($user->account_status),
                $user->isInvitedAccount() ? '—' : ($user->invitation?->accepted_at ?? $user->created_at)?->format('Y-m-d'),
                ['account' => $user],
            ]),
        ]);
    }

    public function user(User $user): View
    {
        $user->load('invitation');

        return view('workspace.admin.user-record', [
            'account' => $user,
            'emailVerifier' => $this->verifier($user, 'email_verified', $user->email_verified_at),
            'phoneVerifier' => $this->verifier($user, 'phone_verified', $user->phone_verified_at),
        ]);
    }

    private function verifier(User $user, string $action, ?Carbon $verifiedAt): ?string
    {
        if ($verifiedAt === null) {
            return null;
        }

        return DB::table('user_account_events as events')->join('users as actors', 'actors.id', '=', 'events.actor_user_id')
            ->where('events.user_id', $user->id)->where('events.action', $action)
            ->where('events.created_at', '>=', $verifiedAt)->orderByDesc('events.id')->value('actors.name');
    }

    public function courses(): View
    {
        $courses = Course::query()->with('category')
            ->orderByDesc('id')->paginate(10);

        return view('workspace.admin.records', [
            'title' => 'Course Manager',
            'description' => 'Review draft and published course records; publication remains subject to academic approval.',
            'columns' => ['Course ID', 'Course', 'Category', 'Level', 'Status'],
            'records' => $courses->through(fn (Course $course) => [
                ['value' => $course->public_id, 'url' => route('admin.course-builder.edit', $course)], $course->title, $course->category?->name, $course->cefr_level ?: 'Any', ucfirst($course->status),
            ]),
        ]);
    }

    public function cefr(): View
    {
        $levels = CefrLevel::query()->orderBy('sort_order')->paginate(10);

        return view('workspace.admin.records', [
            'title' => 'CEFR Level Manager',
            'description' => 'Six reference levels. These are descriptors, not an official examination or test result.',
            'columns' => ['Code', 'Category', 'Level Name', 'Description'],
            'records' => $levels->through(fn (CefrLevel $level) => [
                $level->code, $level->category, $level->name, $level->description,
            ]),
        ]);
    }
}
