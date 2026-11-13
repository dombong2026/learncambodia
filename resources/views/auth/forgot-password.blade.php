@extends('layouts.auth')
@section('title', __('auth.forgot_password'))
@section('auth_heading', __('auth.forgot_password'))
@section('auth_intro', __('auth.forgot_intro'))
@section('content')
<form method="POST" action="{{ route('password.email') }}" class="space-y-4">@csrf
    <x-auth-field id="recovery_email" name="email" type="email" icon="mail" :label="__('auth.email')" autocomplete="email" :value="old('email')" :maxlength="255" required autofocus />
    <button class="auth-primary w-full rounded-xl bg-[#0c2d5b] px-5 py-3 text-sm font-extrabold text-white hover:bg-[#19467c]" type="submit">{{ __('auth.send_reset_link') }}</button>
</form>
<p class="mt-5 text-center text-xs text-slate-600 dark:text-slate-300"><a class="font-bold text-[#0c4a89] transition hover:underline dark:text-[#f2c778]" href="{{ route('login') }}">{{ __('auth.back_to_login') }}</a></p>
@endsection
