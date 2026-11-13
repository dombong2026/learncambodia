@extends('layouts.auth')
@section('title', __('auth.confirm_email'))
@section('auth_heading', __('auth.confirm_email'))
@section('auth_intro', __('auth.confirm_email_intro'))
@section('content')
<p class="mb-5 break-words text-center text-sm font-bold text-slate-700 dark:text-slate-200">{{ auth()->user()->email }}</p>
@if(auth()->user()->hasVerifiedEmail())
    <p class="mb-4 text-sm text-teal-700 dark:text-teal-300">{{ __('auth.email_confirmed') }}</p>
    <a class="auth-primary block rounded-xl bg-[#0c2d5b] px-5 py-3 text-center text-sm font-bold text-white" href="{{ route(auth()->user()->homeRouteName()) }}">{{ __('ui.dashboard') }}</a>
@else
    <form method="POST" action="{{ route('verification.send') }}">@csrf
        <button type="submit" class="auth-primary w-full rounded-xl bg-[#0c2d5b] px-5 py-3 text-sm font-extrabold text-white hover:bg-[#19467c]">{{ __('auth.resend_confirmation') }}</button>
    </form>
@endif
<form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">@csrf
    <button type="submit" class="text-xs font-bold text-slate-600 transition hover:underline dark:text-slate-300">{{ __('ui.sign_out') }}</button>
</form>
@endsection
