@forelse($latestNotices as $notice)
    @include('workspace.notification-item')
@empty
    <p class="px-1 py-3 text-sm text-slate-500 dark:text-slate-400">No notifications yet.</p>
@endforelse
