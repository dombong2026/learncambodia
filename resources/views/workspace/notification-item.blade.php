<form action="{{ route('workspace.notifications.open', $notice->id) }}" method="post">
    @csrf
    <button class="flex w-full items-start gap-3 rounded-xl px-3 py-2 text-left hover:bg-slate-100 dark:hover:bg-slate-800">
        @if(!$notice->read_at)<span class="sr-only">Unread.</span><span aria-hidden="true" class="mt-1.5 size-2 shrink-0 rounded-full bg-blue-600 dark:bg-blue-400"></span>@endif
        <span>
            <span class="block text-sm {{ $notice->read_at ? 'font-medium' : 'font-bold' }}">{{ $notice->data['message'] ?? 'Account update' }}</span>
            <time datetime="{{ $notice->created_at->toAtomString() }}" class="text-xs text-slate-500 dark:text-slate-400">{{ $notice->created_at->diffForHumans() }}</time>
        </span>
    </button>
</form>
