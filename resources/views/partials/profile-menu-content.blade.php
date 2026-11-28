{{-- Shared public and workspace account menu, backed by real role-protected routes. --}}
@auth
    @php
        $workspaceRole = auth()->user()->isAdministrator() ? 'admin' : auth()->user()->role;
        $profileRole = $profileRole ?? $workspaceRole;
        if ($profileRole === 'superadmin' && auth()->user()->isAdministrator()) {
            $profileRole = 'admin';
        }
        $allowedRoles = ['admin', 'instructor', 'student', 'developer'];
        $roleIsKnown = in_array($profileRole, $allowedRoles, true) && $profileRole === $workspaceRole;
        // Deliberately curated frequent destinations, never a copy of the sidebar.
        $shortcutKeys = $roleIsKnown ? match ($profileRole) {
            'admin' => [
                ['courses', 'all'],
                ['examination', 'exams'],
                ['quiz-manager', 'quizzes'],
                ['approvals', 'course-reviews'],
                ['approvals', 'enrollment-reviews'],
                ['courses', 'homework'],
                ['user-manager', 'all'],
            ],
            'student' => [
                ['learning', 'courses'],
                ['learning', 'course-catalog'],
                ['learning', 'saved-courses'],
                ['homework', 'available'],
                ['learning', 'progress'],
                ['assessments', 'practice'],
            ],
            'instructor' => [
                ['teaching', 'courses'],
                ['teaching', 'assignments'],
                ['assessment', 'submissions'],
            ],
            'developer' => [
                ['api-center', 'endpoints'],
                ['credentials', 'tokens'],
                ['operations', 'usage'],
            ],
        } : [];
        $availableShortcuts = [];
        if ($roleIsKnown) {
            foreach (\App\Support\WorkspaceMenu::groups($profileRole, auth()->user()) as $group) {
                foreach ($group['items'] as $entry) {
                    if (!isset($entry['route']) || !\Illuminate\Support\Facades\Route::has($entry['route'])) {
                        continue;
                    }
                    $availableShortcuts[\App\Support\WorkspaceMenu::href($profileRole, $group, $entry)] = true;
                }
            }
        }
        $shortcuts = [];
        foreach ($shortcutKeys as [$section, $key]) {
            $item = \App\Support\WorkspaceMenu::find($profileRole, $section, $key);
            if (!isset($item['route']) || !\Illuminate\Support\Facades\Route::has($item['route'])) {
                continue;
            }
            $href = route($item['route'], $item['parameters'] ?? []);
            if (!isset($availableShortcuts[$href])) {
                continue;
            }
            $shortcuts[$href] = [
                'href' => $href,
                'label' => $item['label'],
                'icon' => \App\Support\WorkspaceMenu::iconFor($section, $key),
            ];
        }
    @endphp
    <div data-profile-divider class="border-b border-slate-200 px-3 py-3 dark:border-slate-700">
        <p class="truncate text-sm font-extrabold text-slate-900 dark:text-white">{{ auth()->user()->name }}</p>
        <p class="truncate text-xs text-slate-500 dark:text-slate-300">{{ auth()->user()->email }}</p>
    </div>
    @if($roleIsKnown)
        <nav data-profile-role-menu="{{ $profileRole }}" aria-label="{{ __('ui.account') }}" class="mt-2">
            <a data-profile-dashboard href="{{ route(auth()->user()->homeRouteName()) }}" class="premium-menu-option flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-extrabold text-[#0d3763] hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-amber-500 dark:text-white dark:hover:bg-slate-800">
                <x-workspace-icon data-profile-link-icon name="layout" class="h-5 w-5 text-blue-700 dark:text-amber-300" />
                <span>{{ __('ui.dashboard') }}</span>
            </a>
            @if(! auth()->user()->hasVerifiedEmail())
                <a href="{{ route('verification.notice') }}" class="premium-menu-option flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-amber-500 dark:hover:bg-slate-800">
                    <x-workspace-icon data-profile-link-icon name="shield-check" class="h-5 w-5" /><span>{{ __('auth.confirm_email') }}</span>
                </a>
            @endif
            @foreach($shortcuts as $item)
                @php
                    $label = app()->getLocale() !== 'en' && __('workspace.labels.'.$item['label']) !== 'workspace.labels.'.$item['label']
                        ? __('workspace.labels.'.$item['label']) : $item['label'];
                @endphp
                <a data-profile-role-link href="{{ $item['href'] }}" class="premium-menu-option flex items-center gap-3 rounded-xl px-3 py-2 text-[13px] leading-5 text-slate-700 transition hover:bg-slate-100 hover:text-blue-800 focus-visible:outline-2 focus-visible:outline-amber-500 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:text-amber-200">
                    <x-workspace-icon data-profile-link-icon :name="$item['icon']" class="h-[18px] w-[18px] text-slate-500 dark:text-slate-300" />
                    <span class="min-w-0 flex-1">{{ $label }}</span>
                </a>
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
<div data-profile-divider class="mt-1 border-t border-slate-200 pt-1 dark:border-slate-700">
    @auth
        <a href="{{ route('home') }}" class="premium-menu-option flex items-center gap-3 rounded-xl px-3 py-2 text-[13px] hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-amber-500 dark:hover:bg-slate-800">
            <x-workspace-icon data-profile-link-icon name="home" class="h-5 w-5 text-slate-500 dark:text-slate-300" /><span>{{ __('workspace.home') }}</span>
        </a>
    @endauth
    @include('partials.theme-toggle', ['showDivider' => false])
    @auth
        <form method="POST" action="{{ route('logout') }}">@csrf
            <button type="submit" class="premium-menu-option flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm text-red-600 hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-amber-500 dark:text-red-400 dark:hover:bg-red-950">
                <x-workspace-icon data-profile-link-icon name="log-out" class="h-5 w-5" /><span>{{ __('ui.sign_out') }}</span>
            </button>
        </form>
    @endauth
</div>
