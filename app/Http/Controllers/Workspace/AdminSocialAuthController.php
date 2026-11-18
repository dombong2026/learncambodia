<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Support\ManagedSocialAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

final class AdminSocialAuthController extends Controller
{
    public function index(Request $request): View
    {
        $selected = $request->query('configure');
        $confirmed = time() - (int) $request->session()->get('auth.password_confirmed_at', 0)
            < config('auth.password_timeout', 10800);
        $providers = [];

        foreach (ManagedSocialAuth::PROVIDERS as $provider => $label) {
            $settings = ManagedSocialAuth::settings($provider);
            $providers[$provider] = [
                'label' => $label,
                'enabled' => ManagedSocialAuth::enabled($settings),
                'configured' => ManagedSocialAuth::configured($settings),
                'source' => $settings['source'],
                'callback' => ManagedSocialAuth::callback($provider),
                'client_id' => $confirmed && $selected === $provider ? $settings['client_id'] : '',
            ];
        }

        return view('workspace.admin.social-auth', [
            'providers' => $providers,
            'confirmed' => $confirmed,
            'selected' => $confirmed && is_string($selected) && isset($providers[$selected]) ? $selected : null,
        ]);
    }

    public function configure(string $provider): RedirectResponse
    {
        return redirect()->to(route('admin.system-settings.social-auth', ['configure' => $provider]).'#social-auth-settings');
    }

    public function confirmStatus(): RedirectResponse
    {
        return to_route('admin.system-settings.social-auth');
    }

    public function status(Request $request, string $provider): RedirectResponse
    {
        $request->validate(['enabled' => ['required', 'boolean']]);
        $enabled = $request->boolean('enabled');
        $label = ManagedSocialAuth::PROVIDERS[$provider];

        if (! ManagedSocialAuth::setEnabled($provider, $enabled)) {
            return to_route('admin.system-settings.social-auth')
                ->with('social_auth_error', "Save $label OAuth credentials before enabling sign-in.");
        }

        return to_route('admin.system-settings.social-auth')
            ->with('status', $label.' sign-in '.($enabled ? 'enabled.' : 'disabled.'));
    }

    public function save(Request $request, string $provider): RedirectResponse
    {
        $validator = Validator::make($request->only('client_id', 'client_secret'), [
            'client_id' => ['required', 'string', 'max:512', 'regex:/\A[\x21-\x7E]+\z/'],
            'client_secret' => ['nullable', 'string', 'max:2048', 'regex:/\A[\x21-\x7E]+\z/'],
        ]);
        $redirect = to_route('admin.system-settings.social-auth', ['configure' => $provider]);
        if ($validator->fails()) {
            return $redirect->with('social_auth_error', $validator->errors()->first());
        }

        $data = $validator->validated();
        $previous = ManagedSocialAuth::settings($provider);
        if (blank($data['client_secret'] ?? null) && $previous['client_id'] === $data['client_id']) {
            $data['client_secret'] = $previous['client_secret'];
        }

        if (blank($data['client_secret'] ?? null)) {
            return $redirect->with('social_auth_error', 'Enter the client secret for this client ID.');
        }

        ManagedSocialAuth::save($provider, $data);

        return $redirect->with('status', ManagedSocialAuth::PROVIDERS[$provider].' settings saved.');
    }
}
