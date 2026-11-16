@extends('layouts.workspace')
@section('title', 'Notifications')
@section('heading', 'Notifications')
@section('heading_action')
    <form action="{{ route('workspace.notifications.read-all') }}" method="post">
        @csrf
        <button class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold dark:border-slate-700">Mark all as read</button>
    </form>
@endsection
@section('content')
    <div class="workspace-panel divide-y divide-slate-200 p-3 dark:divide-slate-700">
        @forelse($notifications as $notice)
            @include('workspace.notification-item')
        @empty
            <p class="p-5 text-sm text-slate-500 dark:text-slate-400">No notifications yet.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $notifications->links() }}</div>
@endsection
