<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Jobs\RunManagedStorageTransfer;
use App\Support\GoogleSignIn;
use App\Support\ManagedStorageSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

final class AdminSystemSettingsController extends Controller
{
    public function branding(): View
    {
        return $this->page('Branding', 'Preview the live brand assets and manage how the site presents them.', 'branding', [
            ['Header', 'Manage the announcement and search placeholder.', 'Edit header', route('admin.appearance.edit', 'header')],
            ['Footer', 'Manage the site-wide footer note.', 'Edit footer', route('admin.appearance.edit', 'footer')],
            ['Typography', 'Manage the site’s font and text settings.', 'Edit typography', route('admin.appearance.edit', 'typography')],
        ]);
    }

    public function security(Request $request): View
    {
        return $this->page('Security', 'Review sign-in and session protections and manage your administrator password.', 'security', [
            ['Administrator password', 'Change the password for the account you are signed in with.', 'Change password', route('admin.profile.edit').'#password'],
            ['Login rate limits', 'Failed sign-in attempts are limited by email address and IP address.', 'Enabled'],
            ['HTTP-only session cookie', 'Prevents browser scripts from reading the session cookie.', config('session.http_only') ? 'Enabled' : 'Disabled'],
            ['HTTPS-only session cookie', 'Controls whether the session cookie is sent only over HTTPS.', config('session.secure') ? 'Enforced' : 'Not forced by configuration'],
        ], ['account' => $request->user(), 'passkeys' => $request->user()->passkeys()->latest()->get()]);
    }

    public function socialAuth(): View
    {
        return $this->page('Social Auth', 'Review the sign-in methods available on LearnCambodia.', 'social-auth', [
            ['Google', 'Use Google to sign in or register a student account.', GoogleSignIn::enabled() ? 'Enabled' : 'Server configuration required'],
            ['Sign-in and registration appearance', 'Edit the copy used on the sign-in and registration screens.', 'Edit appearance', route('admin.appearance.edit', 'auth')],
        ], ['account' => request()->user()]);
    }

    public function cloudStorage(Request $request): View
    {
        $providers = ManagedStorageSettings::PROVIDERS;
        $disk = config('filesystems.managed');
        $rows = [
            ['Active storage', 'Managed media, avatars, and speaking assessment recordings.', $disk === 'local' ? 'Local server' : ($providers[$disk] ?? 'Invalid storage setting')],
        ];
        $profiles = [];
        $ready = [];
        foreach ($providers as $provider => $label) {
            $saved = ManagedStorageSettings::saved($provider);
            $settings = ManagedStorageSettings::effective($provider, $saved);
            $configured = ManagedStorageSettings::configured($provider, $settings);
            $profiles[$provider] = [
                'bucket' => $settings['bucket'] ?? '',
                'endpoint' => $settings['endpoint'] ?? '',
                'region' => $settings['region'] ?? '',
                'storedCredentials' => filled($saved['key'] ?? null),
                'credentialStatus' => [
                    'access_key_id' => filled($saved['key'] ?? null) ? 'Saved' : (filled($settings['key'] ?? null) ? 'Set on server' : 'Not set'),
                    'secret_access_key' => filled($saved['secret'] ?? null) ? 'Saved' : (filled($settings['secret'] ?? null) ? 'Set on server' : 'Not set'),
                ],
            ];
            if ($configured) {
                $ready[$provider] = $label;
            }
            $rows[] = [$label, $configured ? ($saved === null ? 'Configured through the server environment.' : 'Configured through this dashboard.') : 'Add a private bucket to get started.', $configured ? 'Settings present' : 'Settings missing'];
        }

        return $this->page('Cloud Storage', 'Test a bucket connection, copy managed uploads, and verify the destination.', 'cloud-storage', $rows, [
            'providers' => $ready,
            'profiles' => $profiles,
            'activeDisk' => $disk,
            'canEditStorage' => time() - (int) $request->session()->get('auth.password_confirmed_at', 0) < config('auth.password_timeout', 10800),
            'transfers' => DB::table('managed_storage_transfers')->latest('id')->limit(10)->get(),
        ]);
    }

    public function probeCloudStorage(Request $request): RedirectResponse
    {
        $request->mergeIfMissing(['provider' => 'r2']);
        $provider = $request->validate(['provider' => ['required', Rule::in(['r2', 's3'])]])['provider'];
        $redirect = redirect()->route('admin.system-settings.cloud-storage');

        if (!$this->storageConfigured($provider)) {
            return $redirect->withErrors(['probe' => 'Save the bucket settings before testing the connection.']);
        }

        if (Artisan::call('storage:managed-transfer', ['--mode' => 'probe', '--provider' => $provider]) !== 0) {
            return $redirect->withErrors(['probe' => 'The connection test failed. Check the endpoint, credentials, and bucket permissions.']);
        }

        return $redirect->with('status', ($provider === 'r2' ? 'R2' : 'S3').' write, read, and delete checks passed.');
    }

    public function transferCloudStorage(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'provider' => ['required', Rule::in(['r2', 's3'])],
            'operation' => ['required', Rule::in(['copy', 'verify'])],
        ]);
        abort_unless($this->storageConfigured($data['provider']) && config('filesystems.managed') === 'local', 409);

        $lock = Cache::lock('managed-storage-transfer-submit', 30);
        $queued = $lock->get(function () use ($data): bool {
            return DB::transaction(function () use ($data): bool {
                $transfers = DB::table('managed_storage_transfers');
                if ((clone $transfers)->whereIn('status', ['queued', 'running'])->exists()) {
                    return false;
                }

                $id = $transfers->insertGetId($data + [
                    'status' => 'queued', 'created_at' => now(), 'updated_at' => now(),
                ]);
                RunManagedStorageTransfer::dispatch($id)->beforeCommit();

                return true;
            });
        });

        return $queued
            ? back()->with('status', 'Storage operation queued.')
            : back()->withErrors(['probe' => 'A storage operation is already queued or running.']);
    }

    public function configureCloudStorage(): RedirectResponse
    {
        return redirect()->route('admin.system-settings.cloud-storage', ['configure' => 1]);
    }

    public function saveCloudStorage(Request $request): RedirectResponse
    {
        $provider = $request->input('provider');
        abort_unless(is_string($provider) && isset(ManagedStorageSettings::PROVIDERS[$provider]), 422);

        $destination = redirect()->route('admin.system-settings.cloud-storage', ['configure' => 1]);
        $validator = Validator::make($request->only(['bucket', 'endpoint', 'region', 'access_key_id', 'secret_access_key', 'session_token', 'server_credentials']), [
            'bucket' => ['required', 'string', 'min:3', 'max:63', 'regex:/\A(?!\d+\.\d+\.\d+\.\d+\z)(?!.*\.\.)[a-z0-9][a-z0-9.-]+[a-z0-9]\z/'],
            'access_key_id' => ['nullable', 'required_with:secret_access_key,session_token', 'string', 'max:256', 'regex:/\A[A-Za-z0-9]+\z/'],
            'secret_access_key' => ['nullable', 'required_with:access_key_id,session_token', 'string', 'max:256', 'regex:/\A[!-~]+\z/'],
            'session_token' => ['nullable', 'string', 'max:4096', 'regex:/\A[!-~]+\z/'],
            'server_credentials' => ['sometimes', 'boolean'],
        ]);
        if ($provider === 's3' && $request->boolean('server_credentials')) {
            foreach (['access_key_id', 'secret_access_key', 'session_token'] as $field) {
                $validator->addRules([$field => ['prohibited']]);
            }
        }
        $validator->addRules($provider === 'r2' ? [
            'endpoint' => ['required', 'string', 'regex:/\Ahttps:\/\/[a-f0-9]{32}(?:\.(?:eu|us|fedramp))?\.r2\.cloudflarestorage\.com\/?\z/i'],
        ] : [
            'region' => ['required', 'string', 'regex:/\A[a-z]{2}(?:-[a-z0-9]+){1,3}-[0-9]+\z/'],
        ]);
        if ($validator->fails()) {
            return $destination->withErrors($validator, $provider.'Storage');
        }
        $data = $validator->validated();

        return Cache::lock('managed-storage-transfer-submit', 30)->get(function () use ($destination, $provider, $data): RedirectResponse {
            if (config('filesystems.managed') === $provider) {
                return $destination->withErrors(['settings' => 'This provider currently serves uploads. Its settings are locked.'], $provider.'Storage');
            }
            if (DB::table('managed_storage_transfers')->whereIn('status', ['queued', 'running'])->exists()) {
                return $destination->withErrors(['settings' => 'Wait for the current storage transfer to finish.'], $provider.'Storage');
            }

            $saved = ManagedStorageSettings::saved($provider) ?? [];
            $settings = ['bucket' => $data['bucket']] + ($provider === 'r2'
                ? ['endpoint' => rtrim(strtolower($data['endpoint']), '/'), 'region' => 'auto']
                : ['region' => $data['region']]);

            if (filled($data['access_key_id'] ?? null)) {
                $settings += [
                    'key' => $data['access_key_id'],
                    'secret' => $data['secret_access_key'],
                    'token' => $provider === 's3' ? ($data['session_token'] ?? null) : null,
                ];
            } elseif ($provider === 'r2' || !($data['server_credentials'] ?? false)) {
                $settings += array_intersect_key($saved, array_flip(['key', 'secret', 'token']));
            }

            if (!ManagedStorageSettings::configured($provider, ManagedStorageSettings::effective($provider, $settings))) {
                return $destination->withErrors(['settings' => 'Enter the Access Key ID and Secret Access Key for this bucket.'], $provider.'Storage');
            }

            ManagedStorageSettings::save($provider, $settings);

            return $destination
                ->with('status', 'Bucket settings saved. Run the connection test to verify access.')
                ->with('cloud_storage_saved_provider', $provider);
        }) ?: $destination->withErrors(['settings' => 'A storage operation is starting. Try saving again.'], $provider.'Storage');
    }

    private function storageConfigured(string $provider): bool
    {
        $settings = ManagedStorageSettings::effective($provider, ManagedStorageSettings::saved($provider));

        return ManagedStorageSettings::configured($provider, $settings);
    }

    private function page(string $label, string $description, string $section, array $rows, array $extra = []): View
    {
        $translationKey = 'workspace.labels.'.$label;
        $title = trans($translationKey);

        return view('workspace.admin.system-settings', [
            'role' => 'admin',
            'title' => $title === $translationKey ? $label : $title,
            'description' => $description,
            'section' => $section,
            'rows' => $rows,
        ] + $extra);
    }
}
