@extends('layouts.auth')
@section('title', __('auth.create_account'))
@section('auth_heading', app()->getLocale()==='en' ? \App\Support\Appearance::value('auth','register_heading') : __('auth.create_account'))
@section('auth_intro', __('auth.register_intro'))
@section('content')
<form method="POST" action="{{ route('register.store') }}" class="space-y-3.5">@csrf
    <x-auth-field id="register_name" name="name" icon="user-round" :label="__('auth.full_name')" autocomplete="name" :value="old('name')" :maxlength="255" required />
    <x-auth-field id="register_email" name="email" type="email" icon="mail" :label="__('auth.email')" autocomplete="email" :value="old('email')" :maxlength="255" required />
    <x-auth-field id="register_password" name="password" type="password" icon="lock" :label="__('auth.password')" autocomplete="new-password" :minlength="8" required />
    <x-auth-field id="register_password_confirmation" name="password_confirmation" type="password" icon="lock" :label="__('auth.confirm_password')" autocomplete="new-password" :minlength="8" required />
    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs leading-5 text-slate-600 dark:border-[#426084] dark:bg-[#0c2342] dark:text-slate-300">
        <input name="terms" type="checkbox" value="1" @checked(old('terms')) required class="mt-1 rounded border-slate-300">
        <span>{{ __('auth.accept_terms') }} <a href="{{ route('information.show','terms') }}" target="_blank" rel="noopener" class="font-bold text-[#0c4a89] underline dark:text-[#f2c778]">{{ __('auth.terms') }}</a> {{ __('auth.and') }} <a href="{{ route('information.show','privacy') }}" target="_blank" rel="noopener" class="font-bold text-[#0c4a89] underline dark:text-[#f2c778]">{{ __('auth.privacy') }}</a>. <span class="block text-[11px] text-amber-700 dark:text-amber-300">{{ __('auth.policy_draft') }}</span></span>
    </label>
    @error('terms')<p role="alert" class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
    <button type="submit" class="auth-primary w-full rounded-xl bg-[#0c2d5b] px-5 py-3 text-sm font-black text-white transition hover:bg-[#19467c] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#dbad57]">{{ __('auth.create_account') }}</button>
</form>
<p class="mt-4 text-center text-xs leading-6 text-slate-600 dark:text-slate-300">{{ __('auth.have_account') }} <a href="{{ route('login') }}" class="font-extrabold text-[#0c4a89] transition hover:underline dark:text-[#f2c778]">{{ __('auth.sign_in') }}</a></p>
@endsection
