@extends('layouts.auth')
@section('title', __('invitations.accept'))
@section('auth_heading', __('invitations.accept'))
@section('auth_intro', __('invitations.accept_intro'))
@section('content')
    <form method="POST" action="{{ route('invitations.accept', ['token' => $token, 'lang' => app()->getLocale()]) }}" class="space-y-4">
        @csrf
        <x-auth-field id="invite_password" name="password" type="password" icon="lock" :label="__('auth.password')" autocomplete="new-password" :minlength="12" :maxlength="72" :required="true" />
        <x-auth-field id="invite_password_confirmation" name="password_confirmation" type="password" icon="lock" :label="__('auth.confirm_password')" autocomplete="new-password" :minlength="12" :maxlength="72" :required="true" />
        <label class="flex gap-3 text-sm">
            <input name="terms" type="checkbox" value="1" required @checked(old('terms'))>
            <span>{{ __('auth.accept_terms') }}
                <a class="underline" target="_blank" rel="noreferrer" href="{{ route('information.show', 'terms') }}">{{ $publishedPolicies['terms'] ?? __('auth.terms') }}</a>
                {{ __('auth.and') }}
                <a class="underline" target="_blank" rel="noreferrer" href="{{ route('information.show', 'privacy') }}">{{ $publishedPolicies['privacy'] ?? __('auth.privacy') }}</a>.
            </span>
        </label>
        @error('terms')<p role="alert" class="text-sm">{{ $message }}</p>@enderror
        <button type="submit" class="auth-primary w-full rounded-xl bg-[#0c2d5b] px-5 py-3 text-sm font-bold text-white">{{ __('invitations.accept') }}</button>
    </form>
@endsection
