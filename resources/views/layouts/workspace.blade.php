<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#f8fafc">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title', 'Overview') · Learn Cambodia</title>
    @vite(['resources/css/app.css', 'resources/js/app.ts', 'resources/js/workspace.ts'])
</head>
<body class="bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    <a href="#workspace-main" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-[100] focus:rounded-xl focus:bg-white focus:px-4 focus:py-3 focus:text-slate-900">Skip to main content</a>
    <div data-workspace class="workspace-shell min-h-screen lg:pl-[266px]">
        <div data-sidebar-overlay hidden class="fixed inset-0 z-40 bg-slate-950/60 lg:hidden"></div>
        <aside id="workspace-sidebar" data-sidebar class="workspace-sidebar fixed inset-y-0 left-0 z-50 flex w-[266px] -translate-x-full flex-col border-r border-slate-200 bg-white shadow-xl transition-transform duration-200 lg:translate-x-0 lg:shadow-none dark:border-slate-800 dark:bg-slate-900" aria-label="Workspace sidebar">
            <div class="flex h-16 shrink-0 items-center gap-3 border-b border-slate-100 px-5 dark:border-slate-800">
                <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-2.5 font-extrabold tracking-tight">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-blue-700 text-white">L</span>
                    <span class="truncate text-sm">Learn<span class="text-blue-700 dark:text-blue-400">Cambodia</span></span>
                </a>
                <button type="button" data-sidebar-close aria-label="Close navigation" class="ml-auto rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 lg:hidden dark:hover:bg-slate-800"><x-workspace-icon name="close" /></button>
            </div>
            <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <p class="text-[10px] font-bold uppercase tracking-[.17em] text-slate-400">{{ $role === 'admin' ? 'School Administration' : ($role === 'developer' ? 'Developer Workspace' : ($role === 'instructor' ? 'Instructor Workspace' : 'Student Workspace')) }}</p>
                <p class="mt-1 truncate text-sm font-bold">{{ auth()->user()->name }}</p>
            </div>
            <nav data-workspace-nav class="workspace-nav scrollbar-hide min-h-0 flex-1 overflow-y-auto px-3 py-4" aria-label="Account navigation">
                @foreach(\App\Support\WorkspaceMenu::groups($role) as $group)
                    <details data-nav-group class="mb-2 rounded-2xl" {{ $loop->first ? 'open' : '' }}>
                        <summary class="group flex cursor-pointer list-none items-center gap-3 rounded-xl px-3 py-2.5 text-xs font-bold uppercase tracking-[.09em] text-slate-500 transition hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800">
                            <x-workspace-icon :name="$group['icon']" />
                            <span class="min-w-0 flex-1 truncate">{{ $group['title'] }}</span>
                            <x-workspace-icon name="chevron-down" class="nav-chevron h-4 w-4" />
                        </summary>
                        <div class="mt-1 space-y-0.5 pb-1 pl-3">
                        @foreach($group['items'] as $navItem)
                            @php $href = \App\Support\WorkspaceMenu::href($role, $group, $navItem); $active = url()->current() === $href; @endphp
                            <a href="{{ $href }}" @if($active) aria-current="page" @endif data-workspace-link class="nav-item flex items-center gap-2 rounded-lg px-3 py-2 text-[13px] font-medium transition {{ $active ? 'bg-blue-50 font-bold text-blue-700 dark:bg-blue-950 dark:text-blue-300' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white' }}">
                                <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $active ? 'bg-blue-600' : 'bg-slate-300 dark:bg-slate-600' }}"></span>
                                <span class="min-w-0 flex-1">{{ $navItem['label'] }}</span>
                            </a>
                        @endforeach
                        </div>
                    </details>
                @endforeach
                <p data-workspace-no-results hidden class="px-4 py-5 text-sm text-slate-500">No matching navigation items.</p>
            </nav>
            <div class="shrink-0 border-t border-slate-100 p-3 dark:border-slate-800">
                <a href="{{ route('home') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-slate-800 dark:hover:text-white"><x-workspace-icon name="home" /> Back to website</a>
            </div>
        </aside>
        <div class="min-w-0">
            <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur-xl dark:border-slate-800 dark:bg-slate-900/95">
                <div class="flex h-16 items-center gap-3 px-4 sm:px-6 lg:px-8">
                    <button type="button" data-sidebar-toggle aria-label="Open navigation" aria-expanded="false" aria-controls="workspace-sidebar" class="rounded-xl p-2 text-slate-500 hover:bg-slate-100 lg:hidden dark:hover:bg-slate-800"><x-workspace-icon name="menu" /></button>
                    <a href="{{ route('home') }}" aria-label="Home" title="Visit website" class="rounded-xl p-2.5 text-slate-500 transition hover:bg-slate-100 hover:text-blue-700 dark:hover:bg-slate-800 dark:hover:text-blue-400"><x-workspace-icon name="home" /></a>
                    <div class="relative min-w-0 max-w-xl flex-1">
                        <label for="workspace-search" class="sr-only">Search workspace navigation</label>
                        <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"><x-workspace-icon name="search" /></span>
                        <input id="workspace-search" data-workspace-search type="search" autocomplete="off" placeholder="Search workspace menu…" class="w-full rounded-full border border-slate-200 bg-slate-50 py-2.5 pl-11 pr-4 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/15 dark:border-slate-700 dark:bg-slate-950" />
                    </div>
                    <div class="relative" data-dropdown>
                        <button type="button" data-dropdown-trigger aria-expanded="false" aria-controls="workspace-languages" aria-label="Language" class="flex h-10 items-center gap-1 rounded-full border border-slate-200 px-3 text-sm hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">
                            <span aria-hidden="true" class="text-xl">{{ app()->getLocale() === 'km' ? '🇰🇭' : '🇬🇧' }}</span><x-workspace-icon name="chevron-down" />
                        </button>
                        <div id="workspace-languages" data-dropdown-panel hidden class="absolute right-0 mt-2 w-44 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                            <form method="POST" action="{{ route('language.update') }}">@csrf<button type="submit" name="locale" value="en" class="w-full rounded-xl px-3 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-800">🇬🇧 English</button></form>
                            <form method="POST" action="{{ route('language.update') }}">@csrf<button type="submit" name="locale" value="km" class="w-full rounded-xl px-3 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-800">🇰🇭 ភាសាខ្មែរ</button></form>
                        </div>
                    </div>
                    <div class="relative" data-dropdown>
                        <button type="button" data-dropdown-trigger aria-expanded="false" aria-controls="workspace-profile" aria-label="Account menu" class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-blue-700 font-bold text-white transition hover:bg-blue-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700">
                            {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                        </button>
                        <div id="workspace-profile" data-dropdown-panel hidden class="absolute right-0 mt-2 w-56 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                            <div class="border-b border-slate-100 px-3 py-2 dark:border-slate-800"><p class="truncate text-sm font-bold">{{ auth()->user()->name }}</p><p class="truncate text-xs text-slate-500">{{ auth()->user()->email }}</p></div>
                            <a href="{{ route($role === 'admin' ? 'admin.profile.edit' : ($role === 'developer' ? 'developer.profile.edit' : 'account.profile.edit')) }}" class="mt-1 block rounded-xl px-3 py-2.5 text-sm hover:bg-slate-100 dark:hover:bg-slate-800">Profile &amp; Security</a>
                            <a href="{{ \App\Support\WorkspaceMenu::landing($role) }}" class="block rounded-xl px-3 py-2.5 text-sm hover:bg-slate-100 dark:hover:bg-slate-800">Workspace overview</a>
                            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="w-full rounded-xl px-3 py-2.5 text-left text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-950">Sign out</button></form>
                        </div>
                    </div>
                </div>
            </header>
            <main id="workspace-main" class="mx-auto w-full max-w-[1500px] px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
                <div class="mb-8">
                    <p class="mb-2 text-xs font-bold uppercase tracking-[.16em] text-blue-700 dark:text-blue-400">{{ ucfirst($role) }} Workspace</p>
                    <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">@yield('heading', 'Overview')</h1>
                    @hasSection('description')<p class="mt-2 max-w-3xl text-sm leading-7 text-slate-500 dark:text-slate-400">@yield('description')</p>@endif
                </div>
                @if(session('status'))
                    <div role="status" aria-live="polite" data-workspace-toast class="fixed right-4 top-20 z-[80] max-w-sm rounded-2xl border border-emerald-200 bg-white px-5 py-3 text-sm font-semibold text-emerald-800 shadow-xl dark:border-emerald-900 dark:bg-slate-900 dark:text-emerald-300">{{ session('status') }}</div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
