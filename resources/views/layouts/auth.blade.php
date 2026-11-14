<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0c2d5b">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title') · Learn Cambodia</title>
    @include('partials.brand-head')
    @include('partials.theme-boot')
    @vite(['resources/css/app.css','resources/js/app.ts'])
</head>
<body data-auth-body class="min-h-screen bg-gradient-to-br from-[#e9eef5] via-[#f7f9fc] to-[#dfe9f6] text-slate-900 antialiased dark:from-[#071d3b] dark:via-[#0e274a] dark:to-[#0b2446] dark:text-white">
    <a href="#auth-main" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-50 focus:rounded-lg focus:bg-white focus:p-3 focus:text-slate-900">{{ __('ui.skip_content') }}</a>
    <div class="absolute inset-x-0 top-0 h-1 bg-[#e7b857]" aria-hidden="true"></div>
    <main id="auth-main" class="grid min-h-screen place-items-center px-4 py-8 sm:px-6">
        <div class="auth-modal w-full max-w-[440px] overflow-hidden rounded-[26px] border border-slate-200/80 bg-white shadow-2xl shadow-[#0c2d5b]/15 dark:border-[#315078] dark:bg-[#122b4b]">
            <header class="relative bg-gradient-to-br from-[#0e3263] to-[#09254c] px-12 pb-6 pt-7 text-center text-white sm:px-12">
                <a href="{{ route('home') }}" aria-label="{{ __('auth.home') }}" title="{{ __('auth.home') }}" class="absolute left-3 top-4 grid h-9 w-9 place-items-center rounded-lg text-[#ffd078] transition hover:-translate-x-0.5 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#ffd078] sm:left-5 sm:top-5">
                    <x-workspace-icon name="home" class="h-5 w-5" />
                </a>
                <div class="mx-auto flex max-w-xs flex-col items-center text-center">
                    <x-brand-logo surface="dark" height="h-9" imageClass="max-w-[225px]" />
                    <p class="mt-5 text-[10px] font-bold uppercase tracking-[.14em] text-[#f3c368]">{{ __('auth.account_access') }}</p>
                    <h1 class="mt-2 text-[1.65rem] font-black tracking-tight">@yield('auth_heading')</h1>
                    <p class="mt-1.5 text-[13px] leading-6 text-[#d2e1f2]">@yield('auth_intro')</p>
                </div>
            </header>
            <div class="px-6 pb-6 pt-6 sm:px-8">
                @if(session('status'))
                    <p role="status" class="mb-4 rounded-xl border border-teal-300 bg-teal-50 px-4 py-3 text-xs leading-5 text-teal-900 dark:border-teal-800 dark:bg-teal-950 dark:text-teal-200">{{ session('status') }}</p>
                @endif
                @if($errors->any())
                    <p role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs leading-6 text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">{{ __('auth.review_errors') }}</p>
                @endif
                @yield('content')
            </div>
        </div>
    </main>
</body>
</html>
