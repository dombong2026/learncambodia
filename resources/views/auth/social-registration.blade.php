@extends('layouts.auth')
@section('title', 'Finish creating your account')
@section('auth_heading', 'Finish creating your account')
@section('auth_intro', 'Create a student account using '.$label.'.')

@section('content')
    @if(session('social_auth_error'))
        <p role="alert" class="mb-4 text-sm">{{ session('social_auth_error') }}</p>
    @elseif(session('social_registration_sent'))
        <p role="status" class="mb-4 text-sm">We sent a confirmation link to {{ $pending['email'] }}. Open it in this browser to create your account.</p>
    @endif
    <form method="POST" action="{{ route('social.provider.register', $provider) }}" class="space-y-4">
        @csrf
        @if($pending['verified'])
            <p class="break-words text-sm">{{ $pending['email'] }}</p>
        @else
            <label class="block space-y-2 text-sm font-semibold">
                <span>Email address</span>
                <input name="email" type="email" required maxlength="255" autocomplete="email" value="{{ old('email', $pending['email']) }}" class="block w-full rounded-xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-950">
            </label>
            <p class="text-sm">We’ll send you a link to confirm this email address before creating your account.</p>
            @error('email')<p role="alert" class="text-sm">{{ $message }}</p>@enderror
        @endif
        <label class="flex gap-3 text-sm">
            <input name="terms" type="checkbox" value="1" required>
            <span>{{ __('auth.accept_terms') }}
                <a class="underline" target="_blank" rel="noopener" href="{{ route('information.show', 'terms') }}">{{ $publishedPolicies['terms'] ?? __('auth.terms') }}</a>
                {{ __('auth.and') }}
                <a class="underline" target="_blank" rel="noopener" href="{{ route('information.show', 'privacy') }}">{{ $publishedPolicies['privacy'] ?? __('auth.privacy') }}</a>.
            </span>
        </label>
        @error('terms')<p role="alert" class="text-sm">{{ $message }}</p>@enderror
        <button type="submit" class="auth-primary w-full rounded-xl bg-[#0c2d5b] px-5 py-3 text-sm font-bold text-white">{{ $pending['verified'] ? 'Create student account' : 'Send confirmation link' }}</button>
    </form>
@endsection
