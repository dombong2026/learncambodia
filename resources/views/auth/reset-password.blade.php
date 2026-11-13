@extends('layouts.auth')
@section('title', __('auth.reset_password'))
@section('auth_heading', __('auth.reset_password'))
@section('auth_intro', __('auth.reset_intro'))
@section('content')
<form method="POST" action="{{ route('password.update') }}" class="space-y-4">@csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <x-auth-field id="reset_email" name="email" type="email" icon="mail" :label="__('auth.email')" autocomplete="email" :value="old('email', $email)" :maxlength="255" required />
    <x-auth-field id="reset_password" name="password" type="password" icon="lock" :label="__('auth.password')" autocomplete="new-password" :minlength="8" required />
    <x-auth-field id="reset_password_confirmation" name="password_confirmation" type="password" icon="lock" :label="__('auth.confirm_password')" autocomplete="new-password" :minlength="8" required />
    <button class="auth-primary w-full rounded-xl bg-[#0c2d5b] px-5 py-3 text-sm font-extrabold text-white hover:bg-[#19467c]" type="submit">{{ __('auth.reset_password') }}</button>
</form>
@endsection
