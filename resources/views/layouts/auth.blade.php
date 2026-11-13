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
<body class="min-h-screen bg-gradient-to-br from-[#e9eef5] via-[#f7f9fc] to-[#dfe9f6] text-slate-900 antialiased dark:from-[#071d3b] dark:via-[#0e274a] dark:to-[#0b2446] dark:text-white">
    <a href="#auth-main" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-50 focus:rounded-lg focus:bg-white focus:p-3 focus:text-slate-900">{{ __('ui.skip_content') }}</a>
    <div class="absolute inset-x-0 top-0 h-2 bg-[#0c2d5b]" aria-hidden="true"></div>
    <div class="absolute inset-x-0 top-2 h-1 bg-[#e7b857]" aria-hidden="true"></div>
    <main id="auth-main" class="grid min-h-screen place-items-center px-4 py-14 sm:px-6">
        <div class="w-full max-w-[480px] overflow-hidden rounded-[28px] border border-white/80 bg-white shadow-2xl shadow-[#0c2d5b]/15 dark:border-[#315078] dark:bg-[#122b4b]">
            <div class="bg-gradient-to-br from-[#0e3263] to-[#09254c] px-7 pb-7 pt-6 text-white sm:px-9">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <a href="{{ route('home') }}" class="rounded-lg text-lg font-black tracking-tight text-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#ffd47a]">Learn <span class="text-[#ffd078]">Cambodia</span></a>
                    <div class="flex items-center rounded-full border border-white/25 p-0.5" role="group" aria-label="{{ __('ui.language') }}">
                        @foreach(['en'=>'EN','km'=>'ខ្មែរ','zh'=>'中文'] as $locale=>$text)
                            <form method="POST" action="{{ route('language.update') }}">@csrf
                                <button type="submit" name="locale" value="{{ $locale }}" aria-label="{{ config('learning.supported_locales.'.$locale) }}" aria-pressed="{{ app()->getLocale()===$locale ? 'true' : 'false' }}" class="rounded-full px-2.5 py-1.5 text-[11px] font-bold transition {{ app()->getLocale()===$locale ? 'bg-[#f3c368] text-[#0c2d5b]' : 'text-[#d9e6f6] hover:bg-white/10' }}">{{ $text }}</button>
                            </form>
                        @endforeach
                    </div>
                </div>
                <p class="mt-6 text-xs font-bold uppercase tracking-[.12em] text-[#f3c368]">{{ __('auth.account_access') }}</p>
                <h1 class="mt-2 text-[1.8rem] font-black tracking-tight">@yield('auth_heading')</h1>
                <p class="mt-2 text-sm leading-7 text-[#d2e1f2]">@yield('auth_intro')</p>
            </div>
            <div class="px-7 pb-8 pt-7 sm:px-9">
                @if($errors->any())
                    <div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs leading-6 text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                        {{ __('auth.review_errors') }}
                    </div>
                @endif
                @yield('content')
            </div>
        </div>
    </main>
    <a href="{{ route('home') }}" aria-label="{{ __('auth.home') }}" class="fixed bottom-4 left-4 inline-flex items-center gap-2 rounded-lg px-2 py-2 text-xs font-bold text-[#0c2d5b] hover:underline focus-visible:outline-2 focus-visible:outline-[#0c2d5b] dark:text-[#e9c77b]"><x-workspace-icon name="home" class="h-4 w-4" />{{ __('auth.home') }}</a>
</body>
</html>
