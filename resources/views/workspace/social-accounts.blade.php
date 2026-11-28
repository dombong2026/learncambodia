@if(session('social_auth_error'))
    <p role="alert" class="mt-5 rounded-xl border border-red-300 px-4 py-3 text-sm">{{ session('social_auth_error') }}</p>
@endif
<section class="mt-5 space-y-3 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
    <h2 class="text-lg font-bold">Other social accounts</h2>
    @unless($passwordConfirmed)
        <a class="inline-block rounded-xl border border-slate-300 px-4 py-2 text-sm dark:border-slate-600" href="{{ route('social.accounts.confirm') }}">Confirm password to manage social accounts</a>
    @endunless
    @foreach(\Illuminate\Support\Arr::except(\App\Support\ManagedSocialAuth::PROVIDERS, 'google') as $provider => $label)
        @php
            $settings = \App\Support\ManagedSocialAuth::settings($provider);
            $enabled = \App\Support\ManagedSocialAuth::enabled($settings);
            $linked = $socialAccounts->get($provider);
            $changed = $linked !== null && $linked !== \App\Support\ManagedSocialAuth::clientKey($settings);
        @endphp
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-3 dark:border-slate-700">
            <div>
                <p class="text-sm font-bold">{{ $label }}</p>
                <p class="text-sm">{{ $changed ? 'App changed. Reconnect to use this sign-in method.' : ($linked ? ($enabled ? 'Connected' : 'Connected · sign-in disabled') : ($enabled ? 'Available to connect' : 'Unavailable')) }}</p>
            </div>
            @if($passwordConfirmed && ($linked || $enabled))
                <form method="POST" action="{{ route($linked ? 'social.provider.disconnect' : 'social.provider.connect', $provider) }}">
                    @csrf
                    @if($linked) @method('DELETE') @endif
                    <button type="submit" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold dark:border-slate-600">{{ $linked ? 'Disconnect' : 'Connect' }} {{ $label }}</button>
                </form>
            @endif
        </div>
    @endforeach
</section>
