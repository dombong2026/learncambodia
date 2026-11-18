@if(session('social_auth_error'))
    <p role="alert" class="mt-3 text-sm">{{ session('social_auth_error') }}</p>
@endif
@foreach(\App\Support\ManagedSocialAuth::PROVIDERS as $provider => $label)
    @if(\App\Support\ManagedSocialAuth::enabled(\App\Support\ManagedSocialAuth::settings($provider)))
        @php
            $destination = $authReturnTo ? ['next' => $authReturnTo] : [];
            $href = $provider === 'google'
                ? route('social.google.start', $destination)
                : route('social.provider.start', $destination + ['provider' => $provider]);
        @endphp
        <a href="{{ $href }}" class="mt-3 block rounded-xl border border-slate-300 px-5 py-3 text-center text-sm font-semibold dark:border-slate-600">Continue with {{ $label }}</a>
    @endif
@endforeach
