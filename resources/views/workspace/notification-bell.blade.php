@php
    $latestNotices = auth()->user()->notifications()->latest()->limit(5)->get();
    $unreadNotices = auth()->user()->unreadNotifications()->count();
@endphp
<div class="relative shrink-0" data-dropdown data-header-control data-workspace-notifications data-unread-url="{{ route('workspace.notifications.unread') }}">
    <button type="button" data-dropdown-trigger aria-label="{{ $unreadNotices ? 'Notifications, '.$unreadNotices.' unread' : 'Notifications' }}" title="Notifications" aria-expanded="false" aria-controls="workspace-notifications"
        class="premium-header-trigger relative grid h-10 w-10 place-items-center rounded-full text-slate-600 hover:text-blue-700 dark:text-slate-300 dark:hover:text-blue-400">
        <x-workspace-icon name="notification-bell" />
        <span data-notification-count @if(!$unreadNotices) hidden @endif class="absolute right-0 top-0 min-w-4 rounded-full bg-blue-700 px-1 text-center text-[10px] font-bold leading-4 text-white">{{ $unreadNotices > 99 ? '99+' : $unreadNotices }}</span>
    </button>
    <div id="workspace-notifications" data-dropdown-panel hidden class="workspace-notification-panel premium-header-panel absolute right-0 z-50 mt-2 w-80 max-w-[calc(100vw-1rem)] rounded-2xl border border-slate-200 bg-white p-3 shadow-xl dark:border-slate-700 dark:bg-slate-900">
        <div class="mb-2 flex items-center justify-between gap-2 px-1">
            <h2 class="text-sm font-bold">Notifications</h2>
            <a href="{{ route('workspace.notifications') }}" class="text-xs font-semibold text-blue-700 hover:underline dark:text-blue-300">View all</a>
        </div>
        <div data-notification-preview>
            @include('workspace.notification-preview')
        </div>
        <form data-notification-readall @if(!$unreadNotices) hidden @endif action="{{ route('workspace.notifications.read-all') }}" method="post" class="mt-2 border-t border-slate-200 px-1 pt-2 dark:border-slate-700">
            @csrf
            <button class="text-xs font-semibold text-blue-700 hover:underline dark:text-blue-300">Mark all as read</button>
        </form>
    </div>
</div>
