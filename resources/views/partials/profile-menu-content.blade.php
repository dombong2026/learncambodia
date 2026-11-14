{{-- Shared public and workspace account menu, backed by real role-protected routes. --}}
@auth
    @php
        $profileRole = $profileRole ?? auth()->user()->role;
        $allowedRoles = ['admin', 'instructor', 'student', 'developer'];
        $roleIsKnown = in_array($profileRole, $allowedRoles, true) && $profileRole === auth()->user()->role;
        $navGroups = $roleIsKnown ? \App\Support\WorkspaceMenu::groups($profileRole) : [];
    @endphp
    <div class="border-b border-slate-200 px-3 py-3 dark:border-slate-700">
        <p class="truncate text-sm font-extrabold text-slate-900 dark:text-white">{{ auth()->user()->name }}</p>
        <p class="truncate text-xs text-slate-500 dark:text-slate-300">{{ auth()->user()->email }}</p>
    </div>
    @if($roleIsKnown)
        <nav data-profile-role-menu="{{ $profileRole }}" aria-label="{{ __('ui.account') }}" class="mt-2">
            <a data-profile-dashboard href="{{ route(auth()->user()->homeRouteName()) }}" class="premium-menu-option flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-extrabold text-[#0d3763] hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-amber-500 dark:text-white dark:hover:bg-slate-800">
                <x-workspace-icon data-profile-link-icon name="layout" class="h-5 w-5 text-blue-700 dark:text-amber-300" />
                <span>{{ __('ui.dashboard') }}</span>
            </a>
            <a data-profile-settings href="{{ \App\Support\WorkspaceMenu::profile($profileRole) }}" class="premium-menu-option flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-amber-500 dark:hover:bg-slate-800">
                <x-workspace-icon data-profile-link-icon name="shield-check" class="h-5 w-5 text-slate-500 dark:text-slate-300" />
                <span>{{ __('workspace.profile_security') }}</span>
            </a>
            @if(! auth()->user()->hasVerifiedEmail())
                <a href="{{ route('verification.notice') }}" class="premium-menu-option flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-amber-500 dark:hover:bg-slate-800">
                    <x-workspace-icon data-profile-link-icon name="shield-check" class="h-5 w-5" /><span>{{ __('auth.confirm_email') }}</span>
                </a>
            @endif
            @foreach($navGroups as $group)
                <div data-profile-nav-group="{{ $group['key'] }}" class="mt-2 border-t border-slate-200 pt-2 dark:border-slate-700">
                    <h3 class="flex items-center gap-2 px-3 py-2 text-[11px] font-extrabold uppercase tracking-wide text-slate-500 dark:text-slate-300">
                        <x-workspace-icon :name="$group['icon']" class="h-4 w-4" />
                        <span>{{ app()->getLocale()!=='en' && __('workspace.groups.'.$group['key'])!=='workspace.groups.'.$group['key'] ? __('workspace.groups.'.$group['key']) : $group['title'] }}</span>
                    </h3>
                    @foreach($group['items'] as $item)
                        @php
                            $href = \App\Support\WorkspaceMenu::href($profileRole, $group, $item);
                            $label = app()->getLocale()!=='en' && __('workspace.labels.'.$item['label'])!=='workspace.labels.'.$item['label']
                                ? __('workspace.labels.'.$item['label']) : $item['label'];
                        @endphp
                        <a data-profile-role-link href="{{ $href }}" class="premium-menu-option flex items-center gap-3 rounded-xl px-3 py-2 text-[13px] leading-5 text-slate-700 transition hover:bg-slate-100 hover:text-blue-800 focus-visible:outline-2 focus-visible:outline-amber-500 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:text-amber-200">
                            <x-workspace-icon data-profile-link-icon :name="$item['icon'] ?? 'file'" class="h-[18px] w-[18px] text-slate-500 dark:text-slate-300" />
                            <span class="min-w-0 flex-1">{{ $label }}</span>
                        </a>
                    @endforeach
                </div>
            @endforeach
        </nav>
    @endif
@else
    <nav aria-label="{{ __('ui.account') }}">
        <a data-profile-sign-in href="{{ \App\Support\ReadingReturn::authUrl(request(), 'login') }}" class="premium-menu-option flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-amber-500 dark:hover:bg-slate-800">
            <x-workspace-icon data-profile-link-icon data-profile-sign-in-icon name="log-in" class="h-5 w-5 text-slate-500 dark:text-slate-300" /><span>{{ __('ui.sign_in') }}</span>
        </a>
        <a data-profile-register href="{{ \App\Support\ReadingReturn::authUrl(request(), 'register') }}" class="premium-menu-option flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-blue-700 hover:bg-blue-50 focus-visible:outline-2 focus-visible:outline-amber-500 dark:text-blue-300 dark:hover:bg-slate-800">
            <x-workspace-icon data-profile-link-icon data-profile-register-icon name="user-plus" class="h-5 w-5" /><span>{{ __('ui.create_account') }}</span>
        </a>
    </nav>
@endauth
<div class="mt-1 border-t border-slate-200 pt-1 dark:border-slate-700">
    @auth
        <a href="{{ route('home') }}" class="premium-menu-option flex items-center gap-3 rounded-xl px-3 py-2 text-[13px] hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-amber-500 dark:hover:bg-slate-800">
            <x-workspace-icon data-profile-link-icon name="home" class="h-5 w-5 text-slate-500 dark:text-slate-300" /><span>{{ __('workspace.home') }}</span>
        </a>
    @endauth
    @include('partials.theme-toggle')
    @auth
        <form method="POST" action="{{ route('logout') }}">@csrf
            <button type="submit" class="premium-menu-option flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm text-red-600 hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-amber-500 dark:text-red-400 dark:hover:bg-red-950">
                <x-workspace-icon data-profile-link-icon name="log-out" class="h-5 w-5" /><span>{{ __('ui.sign_out') }}</span>
            </button>
        </form>
    @endauth
</div>
