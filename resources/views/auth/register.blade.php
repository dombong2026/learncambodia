@extends('layouts.auth')
@section('title', __('auth.create_account'))
@section('auth_heading', app()->getLocale()==='en' ? \App\Support\Appearance::value('auth','register_heading') : __('auth.create_account'))
@section('auth_intro', __('auth.register_intro'))
@section('content')
<form method="POST" action="{{ route('register.store') }}" class="space-y-4">@csrf
    <div><label for="register_name" class="mb-1.5 block text-xs font-bold">{{ __('auth.full_name') }}</label>
        <div class="relative"><span class="pointer-events-none absolute left-4 top-3.5 text-slate-400"><x-workspace-icon name="user-round" class="h-5 w-5" /></span><input id="register_name" name="name" type="text" maxlength="255" autocomplete="name" value="{{ old('name') }}" required class="w-full rounded-xl border border-slate-300 bg-white py-3 pl-12 pr-4 text-sm outline-none focus:border-[#c6943a] focus:ring-2 focus:ring-[#e3b25a]/35 dark:border-[#426084] dark:bg-[#0c2342]"></div>
        @error('name')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
    </div>
    <div><label for="register_email" class="mb-1.5 block text-xs font-bold">{{ __('auth.email') }}</label>
        <div class="relative"><span class="pointer-events-none absolute left-4 top-3.5 text-slate-400"><x-workspace-icon name="mail" class="h-5 w-5" /></span><input id="register_email" name="email" type="email" maxlength="255" autocomplete="email" value="{{ old('email') }}" required class="w-full rounded-xl border border-slate-300 bg-white py-3 pl-12 pr-4 text-sm outline-none focus:border-[#c6943a] focus:ring-2 focus:ring-[#e3b25a]/35 dark:border-[#426084] dark:bg-[#0c2342]"></div>
        @error('email')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
    </div>
    @foreach(['password','password_confirmation'] as $field)
        <div><label for="register_{{ $field }}" class="mb-1.5 block text-xs font-bold">{{ $field==='password' ? __('auth.password') : __('auth.confirm_password') }}</label>
            <div class="relative"><span class="pointer-events-none absolute left-4 top-3.5 text-slate-400"><x-workspace-icon name="lock" class="h-5 w-5" /></span>
                <input id="register_{{ $field }}" name="{{ $field }}" type="password" autocomplete="new-password" minlength="8" required class="w-full rounded-xl border border-slate-300 bg-white py-3 pl-12 pr-12 text-sm outline-none focus:border-[#c6943a] focus:ring-2 focus:ring-[#e3b25a]/35 dark:border-[#426084] dark:bg-[#0c2342]">
                <button type="button" data-password-toggle data-password-for="register_{{ $field }}" aria-pressed="false" aria-label="{{ __('auth.show_password') }}" data-show-label="{{ __('auth.show_password') }}" data-hide-label="{{ __('auth.hide_password') }}" class="absolute right-2 top-1.5 grid h-10 w-10 place-items-center rounded-xl text-slate-500 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-[#c6943a] dark:hover:bg-[#25456d]"><x-workspace-icon name="eye" class="h-5 w-5" /></button>
            </div>
            @error($field)<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
        </div>
    @endforeach
    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs leading-6 text-slate-600 dark:border-[#426084] dark:bg-[#0c2342] dark:text-slate-300">
        <input name="terms" type="checkbox" value="1" @checked(old('terms')) required class="mt-1 rounded border-slate-300">
        <span>{{ __('auth.accept_terms') }} <a href="{{ route('information.show','terms') }}" target="_blank" rel="noopener" class="font-bold text-[#0c4a89] underline dark:text-[#f2c778]">{{ __('auth.terms') }}</a> {{ __('auth.and') }} <a href="{{ route('information.show','privacy') }}" target="_blank" rel="noopener" class="font-bold text-[#0c4a89] underline dark:text-[#f2c778]">{{ __('auth.privacy') }}</a>. <span class="block text-[11px] text-amber-700 dark:text-amber-300">{{ __('auth.policy_draft') }}</span></span>
    </label>
    @error('terms')<p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
    <button type="submit" class="w-full rounded-xl bg-[#0c2d5b] px-5 py-3.5 text-sm font-black text-white transition hover:bg-[#19467c] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#dbad57]">{{ __('auth.create_account') }}</button>
</form>
<p class="mt-5 text-center text-xs leading-7 text-slate-600 dark:text-slate-300">{{ __('auth.have_account') }} <a href="{{ route('login') }}" class="font-extrabold text-[#0c4a89] hover:underline dark:text-[#f2c778]">{{ __('auth.sign_in') }}</a></p>
@endsection
