@extends('layouts.auth')
@section('title', 'Finish creating your account')
@section('auth_heading', 'Finish creating your account')
@section('auth_intro', 'Review and accept the site policies to create a student account using Google.')
@section('content')
    <form method="POST" action="{{ route('social.google.register') }}" class="space-y-4">
        @csrf
        <label class="flex gap-3 text-sm">
            <input name="terms" type="checkbox" value="1" required>
            <span>{{ __('auth.accept_terms') }}
                <a class="underline" target="_blank" rel="noopener" href="{{ route('information.show','terms') }}">{{ $publishedPolicies['terms'] ?? __('auth.terms') }}</a>
                {{ __('auth.and') }}
                <a class="underline" target="_blank" rel="noopener" href="{{ route('information.show','privacy') }}">{{ $publishedPolicies['privacy'] ?? __('auth.privacy') }}</a>.
            </span>
        </label>
        @error('terms')<p role="alert" class="text-sm">{{ $message }}</p>@enderror
        <button type="submit" class="auth-primary w-full rounded-xl bg-[#0c2d5b] px-5 py-3 text-sm font-bold text-white">Create student account</button>
    </form>
@endsection
