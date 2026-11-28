@extends('layouts.auth')
@section('title', __('auth.login_title'))
@section('auth_heading', app()->getLocale()==='en' ? \App\Support\Appearance::value('auth','login_heading') : __('auth.login_title'))
@section('auth_intro', __('auth.login_intro'))
@section('content')
<form method="POST" action="{{ route('login.store') }}" class="space-y-4">@csrf
    <x-auth-field id="login_email" name="email" type="email" icon="mail" :label="__('auth.email')" autocomplete="email" :value="old('email')" :maxlength="255" :required="true" :autofocus="true" />
    <div>
        <x-auth-field id="login_password" name="password" type="password" icon="lock" :label="__('auth.password')" autocomplete="current-password" :required="true" />
        <div class="mt-2 flex justify-end">
            <a href="{{ route('password.request') }}" class="text-xs font-bold text-[#0c4a89] underline-offset-4 transition hover:text-[#c58b27] hover:underline dark:text-[#f2c778]">{{ __('auth.forgot_password') }}</a>
        </div>
    </div>
    <label class="flex cursor-pointer items-center gap-2.5 text-xs font-semibold text-slate-600 dark:text-slate-300">
        <input type="checkbox" name="remember" value="1" @checked(old('remember')) class="rounded border-slate-300 text-[#0c2d5b]"> {{ __('auth.remember') }}
    </label>
    <button type="submit" class="auth-primary w-full rounded-xl bg-[#0c2d5b] px-5 py-3 text-sm font-black text-white shadow-sm transition hover:bg-[#19467c] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#dbad57]">{{ __('auth.sign_in') }}</button>
</form>
@error('google')<p role="alert" class="mt-3 text-sm">{{ $message }}</p>@enderror
<form data-passkey-login data-options="{{ route('passkey.login-options') }}" data-submit="{{ route('passkey.login') }}" hidden class="mt-3">
    <button type="submit" class="inline-flex w-full items-center justify-center gap-2.5 rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold dark:border-slate-600">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
            <circle cx="7.5" cy="7" r="3"/>
            <path d="M2 20v-2a4 4 0 0 1 4-4h3a4 4 0 0 1 3 1.4M18 12v9h-2m2-3h2"/>
            <circle cx="18" cy="9" r="3"/>
        </svg>
        <span>Sign in with a passkey</span>
    </button>
    <p data-passkey-message role="status" class="mt-2 text-sm"></p>
</form>
@include('auth.social-buttons')
<script src="{{ asset('js/account-passkeys.js') }}" defer></script>
<p class="mt-5 text-center text-xs leading-6 text-slate-600 dark:text-slate-300">{{ __('auth.new_account') }} <a href="{{ route('register', $authReturnTo ? ['next' => $authReturnTo] : []) }}" class="font-extrabold text-[#0c4a89] transition hover:underline dark:text-[#f2c778]">{{ __('auth.create_account') }}</a></p>
@endsection
