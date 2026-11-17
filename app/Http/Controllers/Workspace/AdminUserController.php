<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\CourseInstructorAssignment;
use App\Models\User;
use App\Support\AdminAccess;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AdminUserController extends Controller
{
    public function create(Request $request): View
    {
        return $this->form($request->user(), new User);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateAccount($request);

        try {
            $account = DB::transaction(function () use ($request, $data): User {
                $actor = User::query()->lockForUpdate()->findOrFail($request->user()->id);
                $permissions = $this->authorizedPermissions($actor, $data);
                $account = new User([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => $data['password'],
                ]);
                $account->forceFill(['role' => $data['role'], 'admin_permissions' => $permissions])->save();
                $this->audit($actor, $account, 'unassigned', null, 'Account created through User Manager.');

                return $account;
            });
        } catch (UniqueConstraintViolationException $e) {
            if (! User::where('email', $data['email'])->exists()) {
                throw $e;
            }

            throw ValidationException::withMessages(['email' => __('validation.unique', ['attribute' => 'email'])]);
        }

        return to_route('admin.users.show', $account)->with('status', 'User created.');
    }

    public function editAccess(Request $request, User $user): View
    {
        abort_unless(AdminAccess::canEdit($request->user(), $user), 403);

        return $this->form($request->user(), $user);
    }

    public function updateAccess(Request $request, User $user): RedirectResponse
    {
        abort_unless(AdminAccess::canEdit($request->user(), $user), 403);
        $data = $this->validateAccount($request, true);

        DB::transaction(function () use ($request, $user, $data): void {
            $actorId = $request->user()->id;
            $accounts = User::query()->whereKey([$actorId, $user->id])->orderBy('id')
                ->lockForUpdate()->get()->keyBy('id');
            $actor = $accounts->get($actorId);
            $account = $accounts->get($user->id);
            abort_unless($actor && $account && AdminAccess::canEdit($actor, $account), 403);
            $permissions = $this->authorizedPermissions($actor, $data);
            $previousRole = $account->role;
            $previousPermissions = $account->admin_permissions;

            if ($previousRole === $data['role']
                && ($data['role'] !== User::ROLE_ADMIN || AdminAccess::grants($account) === $permissions)) {
                return;
            }

            if ($previousRole === User::ROLE_INSTRUCTOR && $data['role'] !== User::ROLE_INSTRUCTOR) {
                CourseInstructorAssignment::query()->where('instructor_id', $account->id)
                    ->where('status', 'active')->update(['status' => 'revoked', 'revoked_at' => now()]);
            }

            $account->forceFill(['role' => $data['role'], 'admin_permissions' => $permissions])->save();
            $account->tokens()->delete();
            $this->audit($actor, $account, $previousRole, $previousPermissions, 'Account access updated through User Manager.');
        });

        return to_route('admin.users.show', $user)->with('status', 'User access saved.');
    }

    private function form(User $actor, User $account): View
    {
        return view('workspace.admin.user-form', [
            'account' => $account,
            'roles' => AdminAccess::assignableRoles($actor),
            'permissionOptions' => array_intersect_key(AdminAccess::PERMISSIONS, array_flip(AdminAccess::grants($actor))),
            'selectedPermissions' => $account->exists ? AdminAccess::grants($account) : [],
        ]);
    }

    private function validateAccount(Request $request, bool $editing = false): array
    {
        $rules = [
            'role' => ['required', Rule::in(array_keys(AdminAccess::assignableRoles($request->user())))],
            'permissions' => ['nullable', 'array', 'list'],
            'permissions.*' => ['required', 'string', 'distinct', Rule::in(array_keys(AdminAccess::PERMISSIONS))],
        ];

        if (! $editing) {
            $request->merge(['email' => is_string($request->input('email')) ? mb_strtolower(trim($request->input('email'))) : $request->input('email')]);
            $rules += [
                'name' => ['required', 'string', 'max:120'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['bail', 'required', 'string', 'confirmed', 'max:72', Password::min(12),
                    function (string $attribute, string $value, \Closure $fail): void {
                        if (strlen($value) > 72 || str_contains($value, "\0")) {
                            $fail('The password must fit in 72 bytes and contain no null characters.');
                        }
                    },
                ],
            ];
        }

        return $request->validate($rules);
    }

    private function authorizedPermissions(User $actor, array $data): ?array
    {
        abort_unless(AdminAccess::can($actor, 'users') && array_key_exists($data['role'], AdminAccess::assignableRoles($actor)), 403);

        if ($data['role'] !== User::ROLE_ADMIN) {
            return null;
        }

        $requested = $data['permissions'] ?? [];
        $allowed = AdminAccess::grants($actor);
        abort_if(array_diff($requested, $allowed) !== [], 403);

        return array_values(array_intersect(array_keys(AdminAccess::PERMISSIONS), $requested));
    }

    private function audit(User $actor, User $target, string $previousRole, ?array $previousPermissions, string $reason): void
    {
        DB::table('account_role_changes')->insert([
            'user_id' => $target->id,
            'actor_user_id' => $actor->id,
            'previous_role' => $previousRole,
            'new_role' => $target->role,
            'previous_permissions' => $previousPermissions === null ? null : json_encode($previousPermissions, JSON_THROW_ON_ERROR),
            'new_permissions' => $target->admin_permissions === null ? null : json_encode($target->admin_permissions, JSON_THROW_ON_ERROR),
            'operator_name' => mb_substr($actor->name, 0, 120),
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }
}
