<div class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur-xl dark:border-slate-800 dark:bg-slate-950/95">
    @if(filled(\App\Support\Appearance::value('header','announcement')))
        <div class="border-b border-blue-900 bg-blue-950 px-4 py-2 text-center text-xs font-semibold text-white">{{ \App\Support\Appearance::value('header','announcement') }}</div>
    @endif
    <header class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-3 sm:gap-6 sm:px-6 lg:px-8">
        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2.5 rounded-xl focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-blue-600" aria-label="Learn Cambodia home">
            <span class="grid h-10 w-10 place-items-center rounded-xl bg-blue-700 text-white shadow-sm">
                <svg viewBox="0 0 24 24" class="h-6 w-6" aria-hidden="true" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"><path d="m3 7 9-4 9 4-9 4-9-4Z"/><path d="M7 10v6c3.3 2.8 6.7 2.8 10 0v-6"/><path d="M21 7v8"/></svg>
            </span>
            <span class="text-base font-extrabold tracking-tight sm:text-lg">Learn<span class="text-blue-700 dark:text-blue-400">Cambodia</span></span>
        </a>
        <form action="{{ route('courses.index') }}" method="GET" role="search" class="relative mx-auto hidden min-w-0 w-full max-w-[440px] flex-1 sm:block">
            <label for="global-search" class="sr-only">{{ __('ui.search') }}</label>
            <svg viewBox="0 0 24 24" class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 5 5"/></svg>
            <input id="global-search" name="q" type="search" maxlength="80" value="{{ request('q', '') }}" placeholder="{{ __('frontend.search_placeholder') }}" class="w-full rounded-full border border-slate-200 bg-slate-50 py-2 pl-12 pr-5 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/15 dark:border-slate-700 dark:bg-slate-900">
        </form>
        <div class="ml-auto flex shrink-0 items-center gap-2">
            <div class="relative" data-dropdown>
                <button type="button" data-dropdown-trigger aria-expanded="false" aria-controls="locale-menu" aria-label="{{ __('ui.language') }}" class="flex h-10 items-center gap-1.5 rounded-full border border-transparent bg-transparent px-2 text-sm font-semibold transition hover:-translate-y-0.5 hover:text-blue-600 focus-visible:outline-2 focus-visible:outline-blue-600 dark:hover:text-blue-400">
                    <span class="text-xl" aria-hidden="true">{{ ['en'=>'🇬🇧','km'=>'🇰🇭','zh'=>'🇨🇳'][app()->getLocale()] ?? '🇬🇧' }}</span>
                    <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 text-slate-500" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div id="locale-menu" data-dropdown-panel hidden class="absolute right-0 mt-2 w-44 rounded-2xl border border-slate-200 bg-white p-1.5 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                    <form action="{{ route('language.update') }}" method="POST">@csrf<button type="submit" name="locale" value="en" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-800"><span aria-hidden="true">🇬🇧</span> English</button></form>
                    <form action="{{ route('language.update') }}" method="POST">@csrf<button type="submit" name="locale" value="km" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-800"><span aria-hidden="true">🇰🇭</span> ភាសាខ្មែរ</button></form>
                    <form action="{{ route('language.update') }}" method="POST">@csrf<button type="submit" name="locale" value="zh" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-800"><span aria-hidden="true">🇨🇳</span> 简体中文</button></form>
                </div>
            </div>
            <div class="relative" data-dropdown>
                <button type="button" data-dropdown-trigger aria-expanded="false" aria-controls="profile-menu" aria-label="{{ __('ui.account') }}" class="grid h-10 w-10 place-items-center rounded-full bg-slate-900 text-white transition hover:bg-blue-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 dark:bg-blue-700">
                    <svg viewBox="0 0 24 24" class="h-5 w-5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/></svg>
                </button>
                <div id="profile-menu" data-dropdown-panel hidden class="absolute right-0 mt-2 w-56 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                    @auth
                        <p class="truncate border-b border-slate-100 px-3 py-2 text-xs font-semibold text-slate-500 dark:border-slate-700">{{ auth()->user()->name }}</p>
                        <a href="{{ route(auth()->user()->homeRouteName()) }}" class="block rounded-xl px-3 py-2.5 text-sm hover:bg-slate-100 dark:hover:bg-slate-800">{{ __('ui.dashboard') }}</a>
                        @if(! auth()->user()->hasVerifiedEmail())
                            <a href="{{ route('verification.notice') }}" class="block rounded-xl px-3 py-2.5 text-sm hover:bg-slate-100 dark:hover:bg-slate-800">{{ __('auth.confirm_email') }}</a>
                        @endif
                        <form action="{{ route('logout') }}" method="POST">@csrf<button type="submit" class="block w-full rounded-xl px-3 py-2.5 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-800">{{ __('ui.sign_out') }}</button></form>
                    @else
                        <a href="{{ route('login') }}" class="block rounded-xl px-3 py-2.5 text-sm hover:bg-slate-100 dark:hover:bg-slate-800">{{ __('ui.sign_in') }}</a>
                        <a href="{{ route('register') }}" class="block rounded-xl px-3 py-2.5 text-sm font-semibold text-blue-700 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-slate-800">{{ __('ui.create_account') }}</a>
                    @endauth
                </div>
            </div>
        </div>
    </header>
    <div class="mx-auto px-4 pb-3 sm:hidden">
        <form action="{{ route('courses.index') }}" method="GET" role="search">
            <label for="mobile-search" class="sr-only">{{ __('ui.search') }}</label>
            <input id="mobile-search" name="q" type="search" maxlength="80" placeholder="{{ __('frontend.search_placeholder') }}" class="w-full rounded-full border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-900">
        </form>
    </div>
    <nav aria-label="{{ __('ui.category_navigation') }}" class="border-t border-slate-100 dark:border-slate-800">
        <div class="mx-auto flex max-w-7xl items-center gap-2 px-4 sm:px-6 lg:px-8">
            <button type="button" data-tray-scroll="-1" aria-label="Scroll categories left" class="shrink-0 rounded-full p-2 text-slate-500 transition hover:bg-slate-100 dark:hover:bg-slate-800"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg></button>
            <div data-category-tray class="scrollbar-hide flex min-w-0 flex-1 snap-x gap-6 overflow-x-auto scroll-smooth whitespace-nowrap py-3">
                <a class="snap-start text-sm font-bold text-[#0c2d5b] transition hover:text-[#bd882a] dark:text-[#f7cb77]" href="{{ route('courses.index') }}">{{ __('frontend.nav_courses') }}</a>
                <a class="snap-start text-sm font-semibold text-slate-600 transition hover:text-blue-700 dark:text-slate-300 dark:hover:text-blue-400" href="{{ route('categories.index') }}">{{ __('frontend.all_categories') }}</a>
                <a class="snap-start text-sm font-semibold text-slate-600 transition hover:text-blue-700 dark:text-slate-300 dark:hover:text-blue-400" href="{{ route('articles.index') }}">{{ __('frontend.nav_articles') }}</a>
                @foreach ($navigationCategories as $navigationCategory)
                    <a title="{{ $navigationCategory->name }}" class="snap-start text-sm font-semibold text-slate-600 transition hover:text-blue-700 dark:text-slate-300 dark:hover:text-blue-400" href="{{ route('categories.show', $navigationCategory) }}"><span class="font-extrabold">{{ $navigationCategory->code }}</span><span class="ml-1">{{ $navigationCategory->name }}</span></a>
                @endforeach
                @if(\App\Support\Appearance::value('menu','show_cefr'))
                <a class="snap-start text-sm font-semibold text-slate-600 transition hover:text-blue-700 dark:text-slate-300 dark:hover:text-blue-400" href="{{ route('cefr.index') }}">CEFR A1–C2</a>
                @endif
            </div>
            <button type="button" data-tray-scroll="1" aria-label="Scroll categories right" class="shrink-0 rounded-full p-2 text-slate-500 transition hover:bg-slate-100 dark:hover:bg-slate-800"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg></button>
        </div>
    </nav>
</div>
