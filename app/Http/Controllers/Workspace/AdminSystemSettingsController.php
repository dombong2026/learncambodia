<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
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

    public function security(): View
    {
        return $this->page('Security', 'Review sign-in and session protections and manage your administrator password.', 'security', [
            ['Administrator password', 'Change the password for the account you are signed in with.', 'Change password', route('admin.profile.edit').'#password'],
            ['Login rate limits', 'Failed sign-in attempts are limited by email address and IP address.', 'Enabled'],
            ['HTTP-only session cookie', 'Prevents browser scripts from reading the session cookie.', config('session.http_only') ? 'Enabled' : 'Disabled'],
            ['HTTPS-only session cookie', 'Controls whether the session cookie is sent only over HTTPS.', config('session.secure') ? 'Enforced' : 'Not forced by configuration'],
        ]);
    }

    public function socialAuth(): View
    {
        return $this->page('Social Auth', 'Review the sign-in methods available on LearnCambodia.', 'social-auth', [
            ['Social sign-in providers', 'This installation uses email and password sign-in.', 'No providers connected'],
            ['Sign-in and registration appearance', 'Edit the copy used on the sign-in and registration screens.', 'Edit appearance', route('admin.appearance.edit', 'auth')],
        ]);
    }

    public function cloudStorage(): View
    {
        $disk = config('filesystems.managed');

        return $this->page('Cloud Storage', 'Review where managed uploads are stored and test the private Cloudflare R2 connection.', 'cloud-storage', [
            ['Active storage', 'Managed media, account avatars, and speaking assessment recordings.', match ($disk) {
                'local' => 'Local server',
                'r2' => 'Cloudflare R2',
                default => 'Invalid storage setting',
            }],
            ['R2 configuration', 'The bucket, endpoint, and credentials are managed on the server.', $this->r2IsConfigured() ? 'Settings present' : 'Settings missing'],
        ]);
    }

    public function probeCloudStorage(): RedirectResponse
    {
        $redirect = redirect()->route('admin.system-settings.cloud-storage');

        if (!$this->r2IsConfigured()) {
            return $redirect->withErrors(['probe' => 'Complete the R2 configuration on the server and refresh Laravel’s configuration cache.']);
        }

        if (Artisan::call('storage:managed-transfer', ['--mode' => 'probe']) !== 0) {
            return $redirect->withErrors(['probe' => 'The R2 connection test failed. Check the server’s endpoint, credentials, and bucket permissions.']);
        }

        return $redirect->with('status', 'R2 write, read, and delete checks passed.');
    }

    private function r2IsConfigured(): bool
    {
        $disk = config('filesystems.disks.r2', []);

        return filled($disk['bucket'] ?? null)
            && filled($disk['endpoint'] ?? null)
            && filled($disk['key'] ?? null)
            && filled($disk['secret'] ?? null);
    }

    private function page(string $label, string $description, string $section, array $rows): View
    {
        $translationKey = 'workspace.labels.'.$label;
        $title = trans($translationKey);

        return view('workspace.admin.system-settings', [
            'role' => 'admin',
            'title' => $title === $translationKey ? $label : $title,
            'description' => $description,
            'section' => $section,
            'rows' => $rows,
            'canProbe' => $section === 'cloud-storage' && $this->r2IsConfigured(),
        ]);
    }
}
