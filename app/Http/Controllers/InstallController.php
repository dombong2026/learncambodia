<?php

namespace App\Http\Controllers;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

final class InstallController extends Controller
{
    private function available(): void
    {
        abort_unless(config('installer.enabled') === true, 404);
        $token = config('installer.token');
        abort_unless(is_string($token) && strlen($token) >= 32, 404);
        abort_if(File::exists(config('installer.lock_path')), 404);

        // A configured, partially-used site is never available for takeover.
        try {
            if (Schema::hasTable('users') && User::query()->exists()) {
                abort(404);
            }
            if (Schema::hasTable('courses') && DB::table('courses')->exists()) {
                abort(404);
            }
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            throw $exception;
        } catch (Throwable) {
            abort(503, 'Installer database is not accessible. Configure the database first.');
        }
    }

    private function verified(Request $request): bool
    {
        $token = (string) config('installer.token');
        $session = (string) $request->session()->get('installer.authorized', '');

        return $session !== '' && hash_equals(hash('sha256', $token), $session);
    }

    public function index(Request $request): View
    {
        $this->available();

        return view('install.index', [
            'authorized' => $this->verified($request),
            'database' => config('database.default'),
            'checks' => $this->checks($request),
        ]);
    }

    public function unlock(Request $request): RedirectResponse
    {
        $this->available();
        $validated = $request->validate(['setup_token' => ['required', 'string', 'max:256']]);

        if (! hash_equals((string) config('installer.token'), $validated['setup_token'])) {
            return back()->withErrors(['setup_token' => 'The private setup token is invalid.']);
        }

        $request->session()->regenerate();
        $request->session()->put('installer.authorized', hash('sha256', (string) config('installer.token')));

        return redirect()->route('install.index');
    }

    private function checks(Request $request): array
    {
        $appKey = (string) config('app.key');
        $url = (string) config('app.url');
        $secure = $request->isSecure() || (bool) config('app.env') !== 'production';

        return [
            'key' => str_starts_with($appKey, 'base64:') && strlen($appKey) > 50,
            'database' => in_array(config('database.default'), ['mysql', 'mariadb', 'sqlite'], true),
            'https' => $secure && (config('app.env') !== 'production' || str_starts_with($url, 'https://')),
            'debug' => config('app.env') !== 'production' || !config('app.debug'),
            'storage' => is_writable(storage_path('app')) && is_writable(storage_path('framework')),
            'assets' => File::exists(public_path('build/manifest.json')) || app()->environment('testing'),
        ];
    }

    public function complete(Request $request): RedirectResponse
    {
        $this->available();
        abort_unless($this->verified($request), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
            'confirm_fresh_install' => ['required', 'accepted'],
        ]);

        if (in_array(false, $this->checks($request), true)) {
            return back()->withErrors(['install' => 'The server preflight has not passed. Check application key, database, HTTPS, debug, storage and frontend assets.']);
        }

        $mutexPath = config('installer.mutex_path');
        File::ensureDirectoryExists(dirname($mutexPath), 0700);
        if (is_link($mutexPath) || is_link(config('installer.lock_path'))) {
            abort(503, 'Installer file paths are not safe.');
        }

        $handle = fopen($mutexPath, 'c+');
        abort_unless($handle !== false, 503);

        try {
            abort_unless(flock($handle, LOCK_EX | LOCK_NB), 409, 'Installation is already in progress.');
            $this->available();

            // Laravel migrations are not rolled back automatically across all
            // database engines. Retry remains safe only while there are no users.
            $exit = Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
            if ($exit !== 0) {
                throw new \RuntimeException('Migrations returned non-zero status.');
            }
            $seedExit = Artisan::call('db:seed', [
                '--class' => DatabaseSeeder::class, '--force' => true, '--no-interaction' => true,
            ]);
            if ($seedExit !== 0) {
                throw new \RuntimeException('Reference seeding returned non-zero status.');
            }

            $admin = DB::transaction(function () use ($validated): User {
                if (User::query()->exists()) {
                    throw new \RuntimeException('Application already has accounts.');
                }

                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => $validated['password'],
                ]);
                $user->forceFill([
                    'role' => User::ROLE_ADMIN,
                    'terms_accepted_at' => now(),
                ])->save();

                return $user;
            });

            $result = file_put_contents(config('installer.lock_path'),
                'Installed at '.now()->toIso8601String()."\n", LOCK_EX);
            if ($result === false) {
                throw new \RuntimeException('Install lock could not be written.');
            }
            @chmod(config('installer.lock_path'), 0600);
            $request->session()->forget('installer.authorized');

            Auth::login($admin);
            $request->session()->regenerate();

            return redirect()->route('admin.overview')->with('status', 'Learn Cambodia installation completed successfully.');
        } catch (Throwable $exception) {
            Log::error('Learn Cambodia installer failed', [
                'exception_class' => $exception::class,
            ]);
            return back()->withErrors([
                'install' => 'The installation could not finish. Inspect the private server logs and database; do not open the installer publicly.',
            ]);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
