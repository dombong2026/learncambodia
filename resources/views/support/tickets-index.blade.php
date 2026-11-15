@extends('layouts.workspace')
@section('title','My Support')
@section('heading',$kind==='chat'?'My Live Chats':'My Tickets')
@section('description','Private conversations with the Learn Cambodia support team. Only you and authorized administrators can view them.')
@section('heading_action')
<a href="{{ route('support.tickets.create') }}" class="rounded-xl bg-blue-700 px-5 py-3 text-sm font-bold text-white">Create Ticket</a>
<form method="POST" action="{{ route('support.chat.start') }}">@csrf<button class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-bold dark:border-slate-700">Start Chat</button></form>
@endsection
@section('content')
<div class="mb-5 flex gap-3">
  <a href="{{ route('support.tickets.index',['kind'=>'ticket']) }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-bold dark:border-slate-700">Tickets</a>
  <a href="{{ route('support.tickets.index',['kind'=>'chat']) }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-bold dark:border-slate-700">Chats</a>
  <a href="{{ route('help.index') }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-bold dark:border-slate-700">Help Articles</a>
</div>
<div class="overflow-x-auto rounded-3xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
<table class="w-full min-w-[600px] text-left text-sm">
<thead><tr class="border-b border-slate-200 dark:border-slate-700"><th class="px-5 py-4">Reference</th><th>Subject</th><th>Status</th><th>Updated</th><th class="p-5 text-right">Action</th></tr></thead>
<tbody>
@forelse($tickets as $ticket)
<tr class="border-b border-slate-200 dark:border-slate-700"><td class="px-5 py-4">#{{ $ticket->id }}</td><td class="font-bold">{{ $ticket->subject }}</td><td>{{ ucfirst(str_replace('_',' ',$ticket->status)) }}</td><td>{{ $ticket->updated_at?->format('Y-m-d H:i') }}</td>
<td class="p-5 text-right"><a href="{{ route('support.tickets.show',$ticket) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-xs font-bold dark:border-slate-700">Open</a></td></tr>
@empty<tr><td colspan="5" class="p-8 text-sm text-slate-500">No conversations yet. Use Create Ticket or Start Chat.</td></tr>@endforelse
</tbody></table></div><div class="mt-6">{{ $tickets->links() }}</div>
@endsection