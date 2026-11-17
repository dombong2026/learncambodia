@extends('layouts.auth')
@section('title', 'Verify your sign-in')
@section('auth_heading', 'Verify your sign-in')
@section('auth_intro', 'Enter a code from your authenticator app or use one of your saved recovery codes.')
@section('content')
    <form method="POST" action="{{ route('security.challenge.finish') }}" class="space-y-4">
        @csrf
        <x-auth-field id="security_code" name="code" icon="lock" label="Authenticator or recovery code" autocomplete="one-time-code" maxlength="40" :required="true" :autofocus="true" />
        <button type="submit" class="auth-primary w-full rounded-xl bg-[#0c2d5b] px-5 py-3 text-sm font-bold text-white">Verify and sign in</button>
    </form>
@endsection
