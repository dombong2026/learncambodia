<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Jobs\RunManagedStorageTransfer;
use App\Support\GoogleSignIn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
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

    public function cloudStorage(): View
    {
        $providers = ['r2' => 'Cloudflare R2', 's3' => 'Amazon S3'];
        $disk = config('filesystems.managed');
        $rows = [
            ['Active storage', 'Managed media, avatars, and speaking assessment recordings.', $disk === 'local' ? 'Local server' : ($providers[$disk] ?? 'Invalid storage setting')],
        ];
        foreach ($providers as $provider => $label) {
            $rows[] = [$label, 'Bucket access is configured on the server.', $this->storageConfigured($provider) ? 'Settings present' : 'Settings missing'];
        }

        return $this->page('Cloud Storage', 'Test a bucket connection, copy managed uploads, and verify the destination.', 'cloud-storage', $rows, [
            'providers' => array_filter($providers, fn ($label, $provider) => $this->storageConfigured($provider), ARRAY_FILTER_USE_BOTH),
            'transfers' => DB::table('managed_storage_transfers')->latest('id')->limit(10)->get(),
        ]);
    }

    public function probeCloudStorage(Request $request): RedirectResponse
    {
        $request->mergeIfMissing(['provider' => 'r2']);
        $provider = $request->validate(['provider' => ['required', Rule::in(['r2', 's3'])]])['provider'];
        $redirect = redirect()->route('admin.system-settings.cloud-storage');

        if (!$this->storageConfigured($provider)) {
            return $redirect->withErrors(['probe' => 'Complete the bucket configuration on the server and refresh Laravel’s configuration cache.']);
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

    private function storageConfigured(string $provider): bool
    {
        $disk = config('filesystems.disks.'.($provider === 'r2' ? 'r2' : 'managed-s3'), []);
        $required = $provider === 'r2' ? ['bucket', 'endpoint', 'key', 'secret'] : ['bucket', 'region'];

        return collect($required)->every(fn ($field) => filled($disk[$field] ?? null));
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
