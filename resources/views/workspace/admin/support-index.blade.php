@extends('layouts.workspace')
@section('title',$kind==='chats'?'Live Chat':'Support Tickets')
@section('heading',$kind==='chats'?'Live Chat':'Support Tickets')
@section('description','Private support messages and real recorded ticket statuses. Use Open to respond or update a case.')
@section('heading_action')
<a href="{{ route('support.tickets.create') }}" class="rounded-xl bg-blue-700 px-5 py-3 text-sm font-bold text-white">Create Ticket</a>
<a href="{{ route('help.index') }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-bold dark:border-slate-700">Help Center</a>
@endsection
@section('content')
<form method="GET" class="mb-5 flex flex-wrap items-end gap-3" role="search">
<div><label for="support-admin-query" class="mb-1 block text-xs font-bold">Search</label><input id="support-admin-query" name="q" value="{{ $query }}" maxlength="80" class="rounded-xl border border-slate-300 bg-white p-3 text-sm dark:border-slate-700 dark:bg-slate-900" placeholder="Subject or account name"></div>
<div><label for="support-admin-status" class="mb-1 block text-xs font-bold">Status</label><select id="support-admin-status" name="status" class="rounded-xl border border-slate-300 bg-white p-3 text-sm dark:border-slate-700 dark:bg-slate-900"><option value="">All</option>@foreach(['open','in_progress','resolved','closed'] as $choice)<option value="{{ $choice }}" @selected($status===$choice)>{{ ucfirst(str_replace('_',' ',$choice)) }}</option>@endforeach</select></div><button class="rounded-xl bg-blue-700 px-5 py-3 text-sm font-bold text-white">Apply</button>
<a href="{{ route('admin.support.index',['kind'=>$kind]) }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-bold">Clear</a>
</form>
<div class="overflow-x-auto rounded-3xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"><table class="w-full min-w-[700px] text-left text-sm"><thead><tr class="border-b border-slate-200 dark:border-slate-700"><th class="p-5">Reference</th><th>Subject / Owner</th><th>Status</th><th>Messages</th><th>Updated</th><th class="p-5 text-right">Actions</th></tr></thead><tbody>
@forelse($tickets as $ticket)
<tr class="border-b border-slate-200 dark:border-slate-700"><td class="p-5">#{{ $ticket->id }}</td><td><span class="font-bold">{{ $ticket->subject }}</span><p class="mt-1 text-xs text-slate-500">{{ $ticket->owner?->name }}</p></td><td>{{ ucfirst(str_replace('_',' ',$ticket->status)) }}</td><td>{{ $ticket->messages_count }}</td><td>{{ $ticket->updated_at?->format('Y-m-d H:i') }}</td><td class="p-5 text-right">
<a href="{{ route('support.tickets.show',$ticket) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-xs font-bold dark:border-slate-700">Open</a>
<form method="POST" action="{{ route('admin.support.destroy',['kind'=>$kind,'ticket'=>$ticket]) }}" class="ml-2 inline" onsubmit="return confirm('Archive this conversation?')">@csrf @method('DELETE')<button class="rounded-xl border border-red-300 px-4 py-2 text-xs font-bold text-red-700">Archive</button></form></td></tr>
@empty<tr><td colspan="6" class="p-8 text-sm text-slate-500">No real conversations match your filters.</td></tr>@endforelse
</tbody></table></div><div class="mt-6">{{ $tickets->links() }}</div>
@endsection