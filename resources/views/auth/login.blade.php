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
<p class="mt-5 text-center text-xs leading-6 text-slate-600 dark:text-slate-300">{{ __('auth.new_account') }} <a href="{{ route('register') }}" class="font-extrabold text-[#0c4a89] transition hover:underline dark:text-[#f2c778]">{{ __('auth.create_account') }}</a></p>
@endsection
