<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0c2d5b">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title') · Learn Cambodia</title>
    @vite(['resources/css/app.css','resources/js/app.ts'])
</head>
<body data-auth-body class="min-h-screen bg-gradient-to-br from-[#e9eef5] via-[#f7f9fc] to-[#dfe9f6] text-slate-900 antialiased dark:from-[#071d3b] dark:via-[#0e274a] dark:to-[#0b2446] dark:text-white">
    <a href="#auth-main" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-50 focus:rounded-lg focus:bg-white focus:p-3 focus:text-slate-900">{{ __('ui.skip_content') }}</a>
    <div class="absolute inset-x-0 top-0 h-1 bg-[#e7b857]" aria-hidden="true"></div>
    <main id="auth-main" class="grid min-h-screen place-items-center px-4 py-8 sm:px-6">
        <div class="auth-modal w-full max-w-[440px] overflow-hidden rounded-[26px] border border-slate-200/80 bg-white shadow-2xl shadow-[#0c2d5b]/15 dark:border-[#315078] dark:bg-[#122b4b]">
            <header class="bg-gradient-to-br from-[#0e3263] to-[#09254c] px-6 pb-5 pt-6 text-white sm:px-8">
                <a href="{{ route('home') }}" class="inline-flex rounded-lg text-base font-black tracking-tight text-white transition hover:text-amber-300 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-amber-300">Learn <span class="ml-1 text-[#ffd078]">Cambodia</span></a>
                <p class="mt-5 text-[10px] font-bold uppercase tracking-[.14em] text-[#f3c368]">{{ __('auth.account_access') }}</p>
                <h1 class="mt-2 text-[1.65rem] font-black tracking-tight">@yield('auth_heading')</h1>
                <p class="mt-1.5 text-[13px] leading-6 text-[#d2e1f2]">@yield('auth_intro')</p>
            </header>
            <div class="px-6 pb-6 pt-6 sm:px-8">
                @if(session('status'))
                    <p role="status" class="mb-4 rounded-xl border border-teal-300 bg-teal-50 px-4 py-3 text-xs leading-5 text-teal-900 dark:border-teal-800 dark:bg-teal-950 dark:text-teal-200">{{ session('status') }}</p>
                @endif
                @if($errors->any())
                    <p role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs leading-6 text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">{{ __('auth.review_errors') }}</p>
                @endif
                @yield('content')
                <div class="mt-6 flex items-center justify-between gap-3 border-t border-slate-200 pt-4 text-xs text-slate-500 dark:border-slate-700 dark:text-slate-400">
                    <span>{{ __('auth.secure_session') }}</span>
                    <a href="{{ route('home') }}" aria-label="{{ __('auth.home') }}" title="{{ __('auth.home') }}" class="grid h-9 w-9 place-items-center rounded-lg text-[#0c2d5b] transition hover:-translate-y-0.5 hover:text-[#bd882a] focus-visible:outline-2 focus-visible:outline-[#bd882a] dark:text-[#e9c77b]">
                        <x-workspace-icon name="home" class="h-5 w-5" />
                    </a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
