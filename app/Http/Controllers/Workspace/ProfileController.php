<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\WorkspaceNotifications;
use App\Support\ManagedFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ProfileController extends Controller
{
    public const GENDERS = [
        'female' => 'Woman', 'male' => 'Man', 'nonbinary' => 'Nonbinary', 'other' => 'Other',
        'prefer_not_to_say' => 'Prefer not to say',
    ];

    public const SOCIAL = [
        'facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn',
        'telegram' => 'Telegram', 'tiktok' => 'TikTok', 'youtube' => 'YouTube',
    ];

    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('workspace.profile', [
            'role' => $user->isAdministrator() ? 'admin' : $user->role,
            'details' => $user->profile_details ?? [], 'genders' => self::GENDERS,
            'social' => self::SOCIAL, 'countries' => config('countries'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'first_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'gender' => ['sometimes', 'nullable', Rule::in(array_keys(self::GENDERS))],
            'nationality' => ['sometimes', 'nullable', Rule::in(array_keys(config('countries')))],
            'address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'social' => ['sometimes', 'array:'.implode(',', array_keys(self::SOCIAL))],
            'social.*' => ['nullable', 'url:https,http', 'max:300'],
            'avatar' => ['nullable', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:4096',
                'dimensions:min_width=1,min_height=1,max_width=4096,max_height=4096'],
            'remove_avatar' => ['sometimes', 'boolean', 'prohibits:avatar'],
        ]);

        $disk = ManagedFiles::disk();
        $avatar = $request->file('avatar');
        $path = $avatar ? ManagedFiles::store($avatar, 'account-avatars') : null;
        if ($avatar && !$path) {
            throw ValidationException::withMessages(['avatar' => 'The photo could not be saved. Try again.']);
        }
        $remove = $request->boolean('remove_avatar');
        try {
            $previous = DB::transaction(function () use ($request, $validated, $path, $remove): ?string {
                $account = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                $details = $account->profile_details ?? [];
                foreach (['first_name', 'last_name', 'gender', 'nationality', 'address'] as $field) {
                    if (array_key_exists($field, $validated)) {
                        $details[$field] = $validated[$field];
                    }
                }
                if (isset($validated['social'])) {
                    $details['social'] = array_replace($details['social'] ?? [], $validated['social']);
                }

                $previous = $account->avatar_path;
                $account->forceFill([
                    'name' => $validated['name'],
                    'profile_details' => $details,
                    'avatar_path' => $path ?: ($remove ? null : $previous),
                ])->save();

                return $path || $remove ? $previous : null;
            });
        } catch (Throwable $error) {
            if ($path) {
                $disk->delete($path);
            }
            throw $error;
        }
        if ($previous) {
            $disk->delete($previous);
        }

        return back()->with('status', 'Profile updated successfully.');
    }

    public function avatar(Request $request): Response
    {
        $path = $request->user()->avatar_path;
        $types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        $type = $path ? ($types[pathinfo($path, PATHINFO_EXTENSION)] ?? null) : null;
        abort_unless($type && str_starts_with($path, 'account-avatars/'), 404);

        return ManagedFiles::response($request, $path, [
            'Content-Type' => $type, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ], seekable: true);
    }

    public function password(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->forceFill(['password' => Hash::make($validated['password'])])->save();
        // Rotate all developer tokens when login credentials change.
        $request->user()->tokens()->delete();
        $request->session()->regenerate();
        $prefix = explode('.', $request->user()->homeRouteName())[0];
        WorkspaceNotifications::send($request->user()->id, 'Account password changed', "$prefix.profile.edit");

        return back()->with('status', 'Password updated successfully.');
    }
}
