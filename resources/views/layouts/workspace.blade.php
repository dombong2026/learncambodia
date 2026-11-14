<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#f8fafc">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title', 'Overview') · Learn Cambodia</title>
    @include('partials.brand-head')
    @include('partials.theme-boot')
    @vite(['resources/css/app.css', 'resources/js/app.ts', 'resources/js/workspace.ts'])
</head>
<body data-workspace-body class="bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    <a href="#workspace-main" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-[100] focus:rounded-xl focus:bg-white focus:px-4 focus:py-3 focus:text-slate-900">Skip to main content</a>
    <div data-workspace class="workspace-shell min-h-screen lg:pl-[266px]">
        <div data-sidebar-overlay hidden class="fixed inset-0 z-40 bg-slate-950/60 lg:hidden"></div>
        <aside id="workspace-sidebar" data-sidebar class="workspace-sidebar fixed inset-y-0 left-0 z-50 flex w-[266px] -translate-x-full flex-col border-r border-[#304f7d] bg-[#0c2d5b] text-white shadow-xl transition-transform duration-200 lg:translate-x-0 lg:shadow-none" aria-label="Workspace sidebar">
            <div class="flex h-16 shrink-0 items-center gap-3 border-b border-white/10 px-5">
                <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-2.5 font-extrabold tracking-tight">
                    <x-brand-logo surface="dark" height="h-8" imageClass="max-w-[186px]" />
                </a>
                <button type="button" data-sidebar-close aria-label="Close navigation" class="ml-auto rounded-lg p-1.5 text-white/70 hover:bg-white/10 lg:hidden"><x-workspace-icon name="close" /></button>
            </div>
            <div class="border-b border-white/10 px-5 py-4">
                <p class="text-[10px] font-bold uppercase tracking-[.15em] text-[#efbe59]">{{ __('workspace.groups.overview') }} · {{ __('workspace.workspace') }}</p>
                <p class="mt-1 truncate text-sm font-extrabold text-white">{{ auth()->user()->name }}</p>
            </div>
            <nav data-workspace-nav class="workspace-nav scrollbar-hide min-h-0 flex-1 overflow-y-auto px-3 py-4" aria-label="Account navigation">
                @foreach(\App\Support\WorkspaceMenu::groups($role) as $group)
                    <details data-nav-group class="mb-2 rounded-xl" {{ $loop->first ? 'open' : '' }}>
                        <summary class="group flex cursor-pointer list-none items-center gap-3 rounded-xl px-3 py-2.5 text-[11px] font-extrabold tracking-[.06em] text-[#e9eef8] transition hover:bg-white/10">
                            <x-workspace-icon :name="$group['icon']" />
                            <span class="min-w-0 flex-1 truncate">{{ app()->getLocale()!=='en' && __('workspace.groups.'.$group['key'])!=='workspace.groups.'.$group['key'] ? __('workspace.groups.'.$group['key']) : $group['title'] }}</span>
                            <x-workspace-icon name="chevron-down" class="nav-chevron h-4 w-4" />
                        </summary>
                        <div class="mt-1 space-y-1 pb-2 pl-2">
                        @foreach($group['items'] as $navItem)
                            @php $href = \App\Support\WorkspaceMenu::href($role, $group, $navItem); $active = request()->fullUrl() === $href || url()->current() === $href; @endphp
                            <a href="{{ $href }}" @if($active) aria-current="page" @endif data-workspace-link class="nav-item flex items-center gap-3 rounded-xl border-l-[3px] px-3 py-2.5 text-[13px] font-semibold transition {{ $active ? 'border-[#f9bd4f] bg-[#244a79] font-extrabold text-[#ffd374] shadow-inner' : 'border-transparent text-[#c3d2e7] hover:bg-white/10 hover:text-white' }}">
                                <x-workspace-icon :name="$navItem['icon'] ?? 'file'" class="h-[19px] w-[19px] shrink-0 {{ $active ? 'text-[#ffc65b]' : 'text-[#8fa9ca]' }}" />
                                <span class="min-w-0 flex-1">{{ app()->getLocale()!=='en' && __('workspace.labels.'.$navItem['label'])!=='workspace.labels.'.$navItem['label'] ? __('workspace.labels.'.$navItem['label']) : $navItem['label'] }}</span>
                            </a>
                        @endforeach
                        </div>
                    </details>
                @endforeach
                <p data-workspace-no-results hidden class="px-4 py-5 text-sm text-white/70">{{ __('workspace.search') }}: 0</p>
            </nav>
            <div class="shrink-0 border-t border-white/15 p-3">
                <a href="{{ route('home') }}" class="mb-1 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-[#c3d2e7] transition hover:bg-white/10 hover:text-white"><x-workspace-icon name="home" /> {{ __('workspace.home') }}</a>
                <a data-sidebar-settings href="{{ \App\Support\WorkspaceMenu::profile($role) }}" class="flex items-center gap-3 rounded-xl border-l-[3px] border-[#f4bd4e] bg-white/10 px-3 py-3 text-sm font-bold text-white transition hover:bg-white/20"><x-workspace-icon name="settings" class="h-5 w-5 text-[#ffca65]" /> {{ __('workspace.settings') }}</a>
            </div>
        </aside>
        <div class="min-w-0">
            <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur-xl dark:border-slate-800 dark:bg-slate-900/95">
                <div class="flex h-16 items-center gap-3 px-4 sm:px-6 lg:px-8">
                    <button type="button" data-sidebar-toggle aria-label="Open navigation" aria-expanded="false" aria-controls="workspace-sidebar" class="rounded-xl p-2 text-slate-500 hover:bg-slate-100 lg:hidden dark:hover:bg-slate-800"><x-workspace-icon name="menu" /></button>
                    <a href="{{ route('home') }}" aria-label="Home" title="Visit website" class="rounded-xl p-2.5 text-slate-500 transition hover:bg-slate-100 hover:text-blue-700 dark:hover:bg-slate-800 dark:hover:text-blue-400"><x-workspace-icon name="home" /></a>
                    <div class="relative mx-auto hidden min-w-0 w-full max-w-[380px] flex-1 sm:block">
                        <label for="workspace-search" class="sr-only">Search workspace navigation</label>
                        <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"><x-workspace-icon name="search" /></span>
                        <input id="workspace-search" data-workspace-search type="search" autocomplete="off" placeholder="{{ __('workspace.search') }}…" class="w-full rounded-full border border-slate-200 bg-slate-50 py-2 pl-11 pr-4 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/15 dark:border-slate-700 dark:bg-slate-950" />
                    </div>
                    <button type="button" data-saver-toggle aria-pressed="false" aria-label="{{ __('workspace.data_saver_off') }}" title="{{ __('workspace.data_saver_off') }}" class="flex h-10 shrink-0 items-center gap-1.5 rounded-full border border-transparent bg-transparent px-2 text-xs font-bold text-slate-600 transition hover:-translate-y-0.5 hover:text-blue-700 dark:text-slate-300 dark:hover:text-blue-400">
                        <x-workspace-icon name="activity" />
                        <span class="hidden xl:inline" data-saver-label>{{ __('workspace.data_saver_off') }}</span>
                    </button>
                    @include('partials.language-menu')
                    <div class="relative ml-auto" data-dropdown data-header-control>
                        <button type="button" data-dropdown-trigger aria-expanded="false" aria-controls="workspace-profile" aria-label="Account menu" class="premium-header-trigger grid h-10 w-10 shrink-0 place-items-center rounded-full bg-blue-700 font-bold text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700">
                            {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                        </button>
                        <div id="workspace-profile" data-dropdown-panel hidden class="premium-header-panel absolute right-0 mt-2 w-56 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                            <div class="border-b border-slate-100 px-3 py-2 dark:border-slate-800"><p class="truncate text-sm font-bold">{{ auth()->user()->name }}</p><p class="truncate text-xs text-slate-500">{{ auth()->user()->email }}</p></div>
                            <a href="{{ \App\Support\WorkspaceMenu::profile($role) }}" class="premium-menu-option mt-1 block rounded-xl px-3 py-2.5 text-sm hover:bg-slate-100 dark:hover:bg-slate-800">{{ __('workspace.profile_security') }}</a>
                            @if(! auth()->user()->hasVerifiedEmail())
                                <a href="{{ route('verification.notice') }}" class="premium-menu-option block rounded-xl px-3 py-2.5 text-sm hover:bg-slate-100 dark:hover:bg-slate-800">{{ __('auth.confirm_email') }}</a>
                            @endif
                            <a href="{{ \App\Support\WorkspaceMenu::landing($role) }}" class="block rounded-xl px-3 py-2.5 text-sm hover:bg-slate-100 dark:hover:bg-slate-800">{{ __('workspace.overview') }}</a>
                            @include('partials.theme-toggle')
                            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="premium-menu-option w-full rounded-xl px-3 py-2.5 text-left text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-950">{{ __('workspace.sign_out') }}</button></form>
                        </div>
                    </div>
                </div>
            </header>
            <main id="workspace-main" class="mx-auto w-full max-w-[1500px] px-4 pb-28 pt-5 sm:px-6 sm:pt-7 lg:px-8 lg:pb-10">
                @php
                    $workspaceHeading = trim($__env->yieldContent('heading', __('workspace.overview')));
                    $breadcrumbs = \App\Support\WorkspaceMenu::trail($role, $workspaceHeading);
                    $workspaceAction = \App\Support\WorkspaceMenu::topAction($role);
                @endphp
                <nav aria-label="Breadcrumb" class="mb-5 flex min-w-0 flex-wrap items-center gap-1.5 text-[11px] font-semibold text-slate-500 dark:text-slate-400 sm:text-xs">
                    <a href="{{ \App\Support\WorkspaceMenu::landing($role) }}" class="inline-flex items-center rounded-md p-1 transition hover:text-blue-700 dark:hover:text-blue-400" title="{{ __('workspace.overview') }}" aria-label="{{ __('workspace.overview') }}"><x-workspace-icon name="home" class="h-3.5 w-3.5" /></a>
                    @foreach($breadcrumbs as $crumb)
                        @if($crumb['url'])
                            <x-workspace-icon name="chevron-right" class="h-3 w-3 text-slate-400" />
                            <a href="{{ $crumb['url'] }}" class="rounded-md px-1 py-0.5 transition hover:text-blue-700 hover:underline dark:hover:text-blue-400">{{ $crumb['label'] }}</a>
                        @else
                            @if(!$loop->first)<x-workspace-icon name="chevron-right" class="h-3 w-3 text-slate-400" />@endif
                            <span class="max-w-[270px] truncate px-1 text-slate-700 dark:text-slate-200" aria-current="page">{{ $crumb['label'] }}</span>
                        @endif
                    @endforeach
                </nav>
                <div class="mb-7 flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <p class="mb-1 text-[11px] font-bold uppercase tracking-[.15em] text-blue-700 dark:text-blue-400">{{ ucfirst($role) }} {{ __('workspace.workspace') }}</p>
                        <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">@yield('heading', 'Overview')</h1>
                        @hasSection('description')<p class="mt-2 max-w-3xl text-sm leading-7 text-slate-500 dark:text-slate-400">@yield('description')</p>@endif
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        @hasSection('heading_action')
                            @yield('heading_action')
                        @else
                            <a href="{{ $workspaceAction['url'] }}" class="inline-flex items-center gap-2 rounded-xl bg-blue-700 px-4 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-blue-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 sm:text-sm">{{ $workspaceAction['label'] }}</a>
                        @endif
                    </div>
                </div>
                @if(session('status'))
                    <div role="status" aria-live="polite" data-workspace-toast class="fixed right-4 top-20 z-[80] max-w-sm rounded-2xl border border-emerald-200 bg-white px-5 py-3 text-sm font-semibold text-emerald-800 shadow-xl dark:border-emerald-900 dark:bg-slate-900 dark:text-emerald-300">{{ session('status') }}</div>
                @endif
                @yield('content')
            </main>
        </div>
        <nav class="fixed inset-x-0 bottom-0 z-40 grid grid-cols-5 border-t border-slate-200 bg-white/95 px-2 pb-[env(safe-area-inset-bottom)] shadow-[0_-4px_20px_rgba(15,23,42,.08)] backdrop-blur-xl lg:hidden dark:border-slate-800 dark:bg-slate-900/95" aria-label="Mobile workspace navigation">
            @foreach(\App\Support\WorkspaceMenu::mobileItems($role) as $mobileItem)
                <a href="{{ $mobileItem['url'] }}" @if($mobileItem['active']) aria-current="page" @endif class="flex min-w-0 flex-col items-center gap-1 rounded-xl px-1 py-2.5 text-[10px] font-semibold transition {{ $mobileItem['active'] ? 'text-blue-700 dark:text-blue-400' : 'text-slate-500 hover:text-blue-700 dark:text-slate-400' }}"><x-workspace-icon :name="$mobileItem['icon']" class="h-5 w-5" /><span class="max-w-full truncate">{{ $mobileItem['label'] }}</span></a>
            @endforeach
        </nav>
    </div>
</body>
</html>
