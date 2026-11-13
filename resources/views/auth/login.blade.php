@extends('layouts.auth')
@section('title', __('auth.login_title'))
@section('auth_heading', app()->getLocale()==='en' ? \App\Support\Appearance::value('auth','login_heading') : __('auth.login_title'))
@section('auth_intro', __('auth.login_intro'))
@section('content')
<form method="POST" action="{{ route('login.store') }}" class="space-y-5">@csrf
    <div>
        <label for="login_email" class="mb-2 block text-xs font-bold text-slate-700 dark:text-slate-200">{{ __('auth.email') }}</label>
        <div class="relative"><span class="pointer-events-none absolute left-4 top-3.5 text-slate-400"><x-workspace-icon name="mail" class="h-5 w-5" /></span>
            <input id="login_email" name="email" type="email" autocomplete="email" required autofocus maxlength="255" value="{{ old('email') }}" @class(['w-full rounded-xl border bg-white py-3 pl-12 pr-4 text-sm outline-none focus:ring-2 focus:ring-[#e3b25a]/35 dark:bg-[#0c2342]', 'border-red-400'=> $errors->has('email'),'border-slate-300 focus:border-[#c6943a] dark:border-[#426084]'=> !$errors->has('email')]) aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
        </div>
        @error('email')<p class="mt-2 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="login_password" class="mb-2 block text-xs font-bold text-slate-700 dark:text-slate-200">{{ __('auth.password') }}</label>
        <div class="relative"><span class="pointer-events-none absolute left-4 top-3.5 text-slate-400"><x-workspace-icon name="lock" class="h-5 w-5" /></span>
            <input id="login_password" name="password" type="password" autocomplete="current-password" required class="w-full rounded-xl border border-slate-300 bg-white py-3 pl-12 pr-12 text-sm outline-none focus:border-[#c6943a] focus:ring-2 focus:ring-[#e3b25a]/35 dark:border-[#426084] dark:bg-[#0c2342]">
            <button type="button" data-password-toggle data-password-for="login_password" aria-pressed="false" aria-label="{{ __('auth.show_password') }}" data-show-label="{{ __('auth.show_password') }}" data-hide-label="{{ __('auth.hide_password') }}" class="absolute right-2 top-1.5 grid h-10 w-10 place-items-center rounded-xl text-slate-500 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-[#c6943a] dark:hover:bg-[#25456d]"><x-workspace-icon name="eye" class="h-5 w-5" /></button>
        </div>
        @error('password')<p class="mt-2 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
    </div>
    <label class="flex cursor-pointer items-center gap-2.5 text-xs font-semibold text-slate-600 dark:text-slate-300"><input type="checkbox" name="remember" value="1" @checked(old('remember')) class="rounded border-slate-300 text-[#0c2d5b]"> {{ __('auth.remember') }}</label>
    <button type="submit" class="w-full rounded-xl bg-[#0c2d5b] px-5 py-3.5 text-sm font-black text-white shadow-sm transition hover:bg-[#19467c] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#dbad57]">{{ __('auth.sign_in') }}</button>
</form>
<p class="mt-6 text-center text-xs leading-7 text-slate-600 dark:text-slate-300">{{ __('auth.new_account') }} <a href="{{ route('register') }}" class="font-extrabold text-[#0c4a89] hover:underline dark:text-[#f2c778]">{{ __('auth.create_account') }}</a></p>
@endsection
