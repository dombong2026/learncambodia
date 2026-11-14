<div class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur-xl dark:border-slate-800 dark:bg-slate-950/95">
    @if(filled(\App\Support\Appearance::value('header','announcement')))
        <div class="border-b border-blue-900 bg-blue-950 px-4 py-2 text-center text-xs font-semibold text-white">{{ \App\Support\Appearance::value('header','announcement') }}</div>
    @endif
    <header class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-3 sm:gap-6 sm:px-6 lg:px-8">
        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2.5 rounded-xl focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-blue-600" aria-label="Learn Cambodia home">
            <x-brand-logo height="h-8 sm:h-10" imageClass="max-w-[164px] sm:max-w-[210px]" />
        </a>
        <form action="{{ route('courses.index') }}" method="GET" role="search" class="relative mx-auto hidden min-w-0 w-full max-w-[440px] flex-1 sm:block">
            <label for="global-search" class="sr-only">{{ __('ui.search') }}</label>
            <svg viewBox="0 0 24 24" class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 5 5"/></svg>
            <input id="global-search" name="q" type="search" maxlength="80" value="{{ request('q', '') }}" placeholder="{{ __('frontend.search_placeholder') }}" class="w-full rounded-full border border-slate-200 bg-slate-50 py-2 pl-12 pr-5 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/15 dark:border-slate-700 dark:bg-slate-900">
        </form>
        <div class="ml-auto flex shrink-0 items-center gap-2">
            @include('partials.language-menu')
            <div class="relative" data-dropdown data-header-control>
                <button type="button" data-dropdown-trigger aria-expanded="false" aria-controls="profile-menu" aria-label="{{ __('ui.account') }}" class="premium-header-trigger grid h-10 w-10 place-items-center rounded-full bg-slate-900 text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 dark:bg-blue-700">
                    <svg viewBox="0 0 24 24" class="h-5 w-5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/></svg>
                </button>
                <div id="profile-menu" data-dropdown-panel hidden class="premium-header-panel absolute right-0 mt-2 max-h-[min(75vh,650px)] w-[min(22rem,calc(100vw-2rem))] overflow-y-auto overscroll-contain rounded-2xl border border-slate-200 bg-white p-2 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                    @include('partials.profile-menu-content', ['profileRole' => auth()->user()?->role])
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
