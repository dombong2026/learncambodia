@php
    $passwordConfirmed = time() - (int) session('auth.password_confirmed_at', 0) < config('auth.password_timeout', 10800);
@endphp
@error('code')<p role="alert" class="mt-3 rounded-xl border border-red-300 px-4 py-3 text-sm">{{ $message }}</p>@enderror
<section class="mt-5 space-y-4 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
    <h2 class="text-lg font-bold">Passkeys</h2>
    @forelse($passkeys as $passkey)
        <form class="flex flex-wrap items-center justify-between gap-3" method="POST" action="{{ route('passkey.destroy', $passkey) }}">
            @csrf @method('DELETE')
            <span>{{ $passkey->name }}</span>
            <button class="rounded-xl border border-slate-300 px-4 py-2 text-sm dark:border-slate-600">Remove {{ $passkey->name }}</button>
        </form>
    @empty
        <p class="text-sm">No passkeys registered.</p>
    @endforelse
    @if($passwordConfirmed)
        <form data-passkey-registration data-options="{{ route('passkey.registration-options') }}" data-submit="{{ route('passkey.store') }}" hidden class="space-y-3">
            @csrf
            <label class="block text-sm">Passkey name
                <input name="name" required maxlength="255" class="mt-1 block rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-600 dark:bg-slate-950">
            </label>
            <button class="rounded-xl bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Add passkey</button>
            <p data-passkey-message role="status" class="text-sm"></p>
        </form>
        <p data-passkey-unsupported class="text-sm">Passkey registration requires a supported browser and a secure connection.</p>
    @else
        <a class="inline-block rounded-xl border border-slate-300 px-4 py-2 text-sm dark:border-slate-600" href="{{ route('password.confirm') }}">Confirm password to add a passkey</a>
    @endif
</section>

<section class="mt-5 space-y-4 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
    <h2 class="text-lg font-bold">Authenticator app (2FA)</h2>
    <p class="text-sm">{{ $account->hasEnabledTwoFactorAuthentication() ? 'Enabled' : 'Disabled' }}</p>
    @if(!$passwordConfirmed)
        <a class="inline-block rounded-xl border border-slate-300 px-4 py-2 text-sm dark:border-slate-600" href="{{ route('password.confirm') }}">Confirm password to manage 2FA</a>
    @elseif($account->hasEnabledTwoFactorAuthentication())
        <details class="space-y-2">
            <summary class="cursor-pointer font-semibold">Recovery codes</summary>
            <p class="text-sm">Save these codes somewhere you can access if you lose your authenticator. Each code works once.</p>
            <ul class="font-mono text-sm">
                @foreach($account->recoveryCodes() as $recoveryCode)<li>{{ $recoveryCode }}</li>@endforeach
            </ul>
        </details>
        <form method="POST" action="{{ route('security.factor.recovery') }}" class="space-y-2">
            @csrf
            <label class="block text-sm">Authenticator or recovery code to replace your codes
                <input name="code" required maxlength="40" autocomplete="one-time-code" class="mt-1 block rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-600 dark:bg-slate-950">
            </label>
            <button type="submit" class="rounded-xl border border-slate-300 px-4 py-2 text-sm dark:border-slate-600">Generate new recovery codes</button>
        </form>
        <form method="POST" action="{{ route('security.factor.disable') }}" class="space-y-2">
            @csrf @method('DELETE')
            <label class="block text-sm">Authenticator or recovery code
                <input name="code" required maxlength="40" autocomplete="one-time-code" class="mt-1 block rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-600 dark:bg-slate-950">
            </label>
            <button type="submit" class="rounded-xl border border-slate-300 px-4 py-2 text-sm dark:border-slate-600">Turn off 2FA</button>
        </form>
    @elseif($account->two_factor_secret)
        <p class="text-sm">Scan this code in your authenticator app, then enter the six-digit code.</p>
        <div class="inline-block bg-white p-2">{!! $account->twoFactorQrCodeSvg() !!}</div>
        <p class="text-sm">Manual setup key <code>{{ decrypt($account->two_factor_secret) }}</code></p>
        <form method="POST" action="{{ route('security.factor.confirm') }}" class="space-y-2">
            @csrf
            <label class="block text-sm">Authenticator code
                <input name="code" required pattern="[0-9]{6}" maxlength="6" inputmode="numeric" autocomplete="one-time-code" class="mt-1 block rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-600 dark:bg-slate-950">
            </label>
            <button type="submit" class="rounded-xl bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Confirm 2FA</button>
        </form>
    @else
        <form method="POST" action="{{ route('security.factor.enable') }}">
            @csrf
            <button type="submit" class="rounded-xl bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Set up 2FA</button>
        </form>
    @endif
</section>
