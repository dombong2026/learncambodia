<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Support\ManagedSmtpSettings;
use App\Support\OutboundMail;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

final class AdminSmtpController extends Controller
{
    public function edit(Request $request): View
    {
        if (time() - (int) $request->session()->get('auth.password_confirmed_at', 0) >= config('auth.password_timeout', 10800)) {
            return view('workspace.admin.smtp', ['canManage' => false]);
        }

        $unreadable = false;
        try {
            $saved = ManagedSmtpSettings::saved();
        } catch (DecryptException|\JsonException) {
            $unreadable = true;
            $saved = null;
        }

        $settings = $saved ?? $this->serverSettings();
        $hasPassword = filled($settings['password']);

        return view('workspace.admin.smtp', [
            'canManage' => true,
            'settings' => Arr::except($settings, ['password']),
            'hasPassword' => $hasPassword,
            'source' => $unreadable ? 'Unreadable dashboard settings' : ($saved ? 'Dashboard SMTP' : 'Server configuration'),
            'dashboardSaved' => $saved !== null || $unreadable,
            'canSend' => OutboundMail::deliveryEnabled(),
            'email' => $request->user()->email,
        ]);
    }

    public function configure(): RedirectResponse
    {
        return to_route('admin.system-settings.smtp');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'host' => ['required', 'string', 'max:253', 'regex:/\A(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\z/i'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'scheme' => ['required', Rule::in(['smtp', 'smtps'])],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:1024'],
            'from_address' => ['required', 'email:rfc', 'max:255'],
            'from_name' => ['required', 'string', 'max:120', 'not_regex:/[\r\n]/'],
        ]);

        $data['host'] = strtolower($data['host']);
        $data['port'] = (int) $data['port'];
        $data['username'] = $data['username'] ?? null;
        $data['password'] = $data['password'] ?? null;
        if (filled($data['username']) && blank($data['password'])) {
            try {
                $previous = ManagedSmtpSettings::saved() ?? $this->serverSettings();
            } catch (DecryptException|\JsonException) {
                $previous = [];
            }
            $connection = array_flip(['host', 'port', 'scheme', 'username']);
            if (array_intersect_key($data, $connection) == array_intersect_key($previous, $connection)) {
                $data['password'] = $previous['password'] ?? null;
            }
        }

        if (filled($data['username']) !== filled($data['password'])) {
            return back()->withErrors(['password' => 'Enter the SMTP username and password together. A new server or username requires the matching password.'])
                ->withInput(Arr::except($data, ['password']));
        }

        ManagedSmtpSettings::save($data);
        $this->audit($request, 'saved');

        return to_route('admin.system-settings.smtp')->with('status', 'SMTP settings saved. Send a test email to check delivery.');
    }

    public function reset(Request $request): RedirectResponse
    {
        ManagedSmtpSettings::reset();
        $this->audit($request, 'reset');

        return to_route('admin.system-settings.smtp')->with('status', 'Server mail configuration is active.');
    }

    public function test(Request $request): RedirectResponse
    {
        $data = $request->validate(['recipient' => ['required', 'email:rfc', 'max:255']]);
        $redirect = to_route('admin.system-settings.smtp');

        try {
            if (! OutboundMail::deliveryEnabled()) {
                return $redirect->withErrors(['recipient' => 'Save an outbound mail configuration before sending a test email.']);
            }

            $sent = Mail::raw('This is a test email from LearnCambodia System Settings.', function ($message) use ($data): void {
                $message->to($data['recipient'])->subject('LearnCambodia SMTP test');
            });
            if ($sent === null) {
                return $redirect->withErrors(['recipient' => 'The mailer cancelled the test. Check the active transport and try again.']);
            }
        } catch (\Exception $error) {
            Log::warning('Dashboard SMTP test failed.', [
                'actor_id' => $request->user()->getKey(),
                'error_type' => get_class($error),
                'error_code' => (int) $error->getCode(),
            ]);

            return $redirect->withErrors(['recipient' => $this->deliveryError($error)]);
        }

        $this->audit($request, 'test accepted');

        return $redirect->with('status', 'The mail server accepted the test email. Check the recipient inbox and spam folder.');
    }

    private function serverSettings(): array
    {
        $smtp = config('mail.mailers.smtp');
        $port = (int) ($smtp['port'] ?? 587);

        return [
            'host' => strtolower((string) ($smtp['host'] ?? '')),
            'port' => $port,
            'scheme' => $smtp['scheme'] ?? ($port === 465 ? 'smtps' : 'smtp'),
            'username' => $smtp['username'] ?? null,
            'password' => empty($smtp['url']) ? ($smtp['password'] ?? null) : null,
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
        ];
    }

    private function audit(Request $request, string $action): void
    {
        Log::info('Dashboard SMTP '.$action.'.', ['actor_id' => $request->user()->getKey()]);
    }

    private function deliveryError(\Exception $error): string
    {
        if ($error instanceof \InvalidArgumentException) {
            return 'Laravel could not build the mail transport. Review the host, port, encryption, and sender address.';
        }

        if (! $error instanceof TransportExceptionInterface) {
            return 'The test email could not be prepared. Check the application log for the error type.';
        }

        $message = $error->getMessage();
        if (preg_match('/\b53[45](?:[ -]5\.7\.[89]\d*|\b)/', $message)) {
            return 'SMTP authentication was rejected. Check the username and provider app password.';
        }
        if (preg_match('/certificate|starttls|ssl|tls handshake/i', $message)) {
            return 'A secure SMTP connection could not be established. Check the encryption mode, port, and server certificate.';
        }
        if (preg_match('/disconnect|timed out|connection|refused|socket|network|stream/i', $message)) {
            return 'The SMTP connection closed or could not be opened. Check the host, port, and outbound access from this server.';
        }

        return 'The SMTP server rejected the test email. Check the sender address and your provider’s mail logs.';
    }
}
