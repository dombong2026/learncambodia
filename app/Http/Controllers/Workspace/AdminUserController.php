<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\CourseInstructorAssignment;
use App\Models\User;
use App\Support\AccountInvitations;
use App\Support\AdminAccess;
use App\Support\ManagedAccount;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AdminUserController extends Controller
{
    public function create(Request $request): View
    {
        return $this->form($request->user(), new User, $request->query('method') === 'invite');
    }

    public function store(Request $request): RedirectResponse
    {
        $sendInvitation = $request->input('method') === 'invite';
        if ($sendInvitation && ! AccountInvitations::deliveryEnabled()) {
            return back()->withErrors(['method' => __('invitations.delivery_unavailable')])->withInput();
        }
        $data = $this->validateAccount($request, sendInvitation: $sendInvitation);

        try {
            [$account, $invitation] = DB::transaction(function () use ($request, $data, $sendInvitation): array {
                $actor = User::query()->lockForUpdate()->findOrFail($request->user()->id);
                $permissions = $this->authorizedPermissions($actor, $data);
                $account = new User([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => $sendInvitation ? Str::random(64) : $data['password'],
                ]);
                $account->forceFill([
                    'role' => $data['role'],
                    'account_status' => $sendInvitation ? 'invited' : 'active',
                    'admin_permissions' => $permissions,
                    'phone' => $data['phone'] ?? null,
                    'phone_verified_at' => ! empty($data['verify_phone']) ? now() : null,
                    'email_verified_at' => ! empty($data['verify_email']) ? now() : null,
                ])->save();
                if ($account->hasVerifiedEmail()) {
                    ManagedAccount::audit($actor, $account, 'email_verified');
                }
                if ($account->phone_verified_at) {
                    ManagedAccount::audit($actor, $account, 'phone_verified');
                }
                $this->audit($actor, $account, 'unassigned', null, 'Account created through User Manager.');

                $invitation = $sendInvitation ? AccountInvitations::issue($actor, $account, $data['invite_locale']) : null;

                return [$account, $invitation];
            }, 3);
        } catch (UniqueConstraintViolationException $e) {
            if (! User::where('email', $data['email'])->exists()) {
                throw $e;
            }

            throw ValidationException::withMessages(['email' => __('validation.unique', ['attribute' => 'email'])]);
        }

        return to_route('admin.users.show', $account)->with(
            'status', $invitation ? AccountInvitations::send(...$invitation) : 'User created.'
        );
    }

    public function editAccess(Request $request, User $user): View
    {
        abort_unless(AdminAccess::canEdit($request->user(), $user) && $user->account_status !== 'deleted', 403);

        return $this->form($request->user(), $user);
    }

    public function updateAccess(Request $request, User $user): RedirectResponse
    {
        abort_unless(AdminAccess::canEdit($request->user(), $user) && $user->account_status !== 'deleted', 403);
        $data = $this->validateAccount($request, $user);

        try {
            DB::transaction(function () use ($request, $user, $data): void {
                [$actor, $account] = ManagedAccount::lockPair($request, $user);
                abort_if($account->account_status === 'deleted', 403);
                $permissions = $this->authorizedPermissions($actor, $data);
                $previousRole = $account->role;
                $previousPermissions = $account->admin_permissions;
                $accessChanged = $previousRole !== $data['role']
                    || ($data['role'] === User::ROLE_ADMIN && AdminAccess::grants($account) !== $permissions);

                $account->fill(array_intersect_key($data, ['name' => true, 'email' => true]));
                $emailChanged = $account->isDirty('email');
                if ($emailChanged) {
                    $account->forceFill(['email_verified_at' => null, 'google_subject' => null]);
                }
                if (array_key_exists('phone', $data) && $account->phone !== $data['phone']) {
                    $account->forceFill(['phone' => $data['phone'], 'phone_verified_at' => null]);
                }
                $detailsChanged = $account->isDirty();
                if ($previousRole === User::ROLE_INSTRUCTOR && $data['role'] !== User::ROLE_INSTRUCTOR) {
                    CourseInstructorAssignment::query()->where('instructor_id', $account->id)
                        ->where('status', 'active')->update(['status' => 'revoked', 'revoked_at' => now()]);
                }

                if ($accessChanged || $emailChanged) {
                    ManagedAccount::revokeSessions($account);
                    if ($account->isInvitedAccount()) {
                        AccountInvitations::revoke($actor, $account);
                    }
                }
                if ($accessChanged) {
                    $account->forceFill(['role' => $data['role'], 'admin_permissions' => $permissions]);
                    $this->audit($actor, $account, $previousRole, $previousPermissions, 'Account access updated through User Manager.');
                }
                if ($detailsChanged) {
                    ManagedAccount::audit($actor, $account, 'details_updated');
                }
                $account->save();
            });
        } catch (UniqueConstraintViolationException $e) {
            if (! isset($data['email']) || ! User::where('email', $data['email'])->whereKeyNot($user->id)->exists()) {
                throw $e;
            }

            throw ValidationException::withMessages(['email' => __('validation.unique', ['attribute' => 'email'])]);
        }

        return to_route('admin.users.show', $user)->with('status', 'User access saved.');
    }

    private function form(User $actor, User $account, bool $sendInvitation = false): View
    {
        return view('workspace.admin.user-form', [
            'account' => $account,
            'sendInvitation' => $sendInvitation,
            'invitationDeliveryEnabled' => AccountInvitations::deliveryEnabled(),
            'roles' => AdminAccess::assignableRoles($actor),
            'permissionOptions' => array_intersect_key(AdminAccess::PERMISSIONS, array_flip(AdminAccess::grants($actor))),
            'selectedPermissions' => $account->exists ? AdminAccess::grants($account) : [],
        ]);
    }

    private function validateAccount(Request $request, ?User $editing = null, bool $sendInvitation = false): array
    {
        $rules = [
            'role' => ['required', Rule::in(array_keys(AdminAccess::assignableRoles($request->user())))],
            'permissions' => ['nullable', 'array', 'list'],
            'permissions.*' => ['required', 'string', 'distinct', Rule::in(array_keys(AdminAccess::PERMISSIONS))],
        ];

        if (is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower(trim($request->input('email')))]);
        }
        if (is_string($request->input('phone'))) {
            $request->merge(['phone' => preg_replace('/[\s().-]+/', '', $request->input('phone')) ?: null]);
        }

        $presence = $editing ? 'sometimes' : 'required';
        $emailRule = Rule::unique('users', 'email');
        if ($editing) {
            $emailRule->ignore($editing);
        }
        $rules += [
            'name' => [$presence, 'required', 'string', 'max:120'],
            'email' => [$presence, 'required', 'email', 'max:255', $emailRule],
            'phone' => ['nullable', 'required_if:verify_phone,1', 'regex:/^\+[1-9]\d{7,14}$/D'],
        ];

        if ($sendInvitation) {
            $rules['invite_locale'] = ['required', Rule::in(array_keys(config('learning.supported_locales')))];
        } elseif (! $editing) {
            $rules += [
                'verify_email' => ['sometimes', 'boolean'],
                'verify_phone' => ['sometimes', 'boolean'],
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
        abort_unless($actor->isActiveAccount() && AdminAccess::can($actor, 'users') && array_key_exists($data['role'], AdminAccess::assignableRoles($actor)), 403);

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
