@extends('layouts.auth')
@section('title', 'Confirm your password')
@section('auth_heading', 'Confirm your password')
@section('auth_intro', 'Enter your current password to manage sign-in methods or run a storage transfer.')
@section('content')
    <form method="POST" action="{{ route('security.password.confirm') }}" class="space-y-4">
        @csrf
        <x-auth-field id="security_password" name="password" type="password" icon="lock" label="Current password" autocomplete="current-password" :required="true" :autofocus="true" />
        <button type="submit" class="auth-primary w-full rounded-xl bg-[#0c2d5b] px-5 py-3 text-sm font-bold text-white">Confirm password</button>
    </form>
@endsection
