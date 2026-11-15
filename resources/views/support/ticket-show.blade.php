@extends('layouts.workspace')
@section('title',$ticket->subject)
@section('heading',$ticket->kind==='chat'?'Live Chat #'.$ticket->id:'Ticket #'.$ticket->id)
@section('description',$ticket->subject.' · '.ucfirst(str_replace('_',' ',$ticket->status)).' · Private support conversation.')
@section('heading_action')
<a href="{{ auth()->user()->role==='admin' ? route('admin.support.index',['kind'=>$ticket->kind==='chat'?'chats':'tickets']) : route('support.tickets.index',['kind'=>$ticket->kind]) }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-bold dark:border-slate-700">All</a>
<button type="button" data-support-refresh class="rounded-xl bg-blue-700 px-5 py-3 text-sm font-bold text-white">Refresh Messages</button>
@endsection
@section('content')
<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_300px]">
<section class="min-w-0 rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
    <h2 class="text-lg font-extrabold">Conversation</h2>
    <p class="mt-1 text-xs text-slate-500">New messages refresh automatically about every 8 seconds while this page is open.</p>
    <div data-support-feed data-last="{{ $messages->getCollection()->max('id') ?? 0 }}" class="mt-5 space-y-4" aria-live="polite">
    @forelse($messages as $message)
        <article data-message-id="{{ $message->id }}" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-950">
            <div class="flex flex-wrap justify-between gap-3 text-xs font-bold text-blue-700 dark:text-blue-400"><span>{{ $message->author?->name ?? 'Account' }}</span><time>{{ $message->created_at?->format('Y-m-d H:i') }}</time></div>
            <div class="rich-public-content mt-2 break-words text-sm leading-7">@if($message->body_html){!! \App\Support\EditorHtml::clean($message->body_html) !!}@else<span class="whitespace-pre-line">{{ $message->body }}</span>@endif</div>
        </article>
    @empty<p class="text-sm text-slate-500">No messages yet. Send the first message to begin.</p>@endforelse
    </div>
    <div class="mt-5">{{ $messages->links() }}</div>
    @if($ticket->status!=='closed')
        <form method="POST" action="{{ route('support.messages.store',$ticket) }}" class="mt-6 space-y-3">@csrf
            <label for="support-reply" class="block text-sm font-bold">Send a message</label>
            <x-rich-editor id="support-reply" :value="old('body','')" :html="old('body_html')" context="ticket" :max-length="4000"/>
            @error('body')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            <button type="submit" class="rounded-xl bg-blue-700 px-6 py-3 text-sm font-bold text-white">Send Message</button>
        </form>
    @else
        <p class="mt-6 text-sm text-slate-500">This conversation is closed. An administrator can reopen it.</p>
    @endif
</section>
<aside class="space-y-4">
@if(auth()->user()->role==='admin')
    <form id="admin-support-update" method="POST" action="{{ route('admin.support.update',['kind'=>$ticket->kind==='chat'?'chats':'tickets','ticket'=>$ticket]) }}" class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">@csrf @method('PATCH')
        <h2 class="font-bold">Manage Conversation</h2>
        <p class="break-words text-xs text-slate-500">Owner: {{ $ticket->owner?->name }} · {{ $ticket->owner?->email }}</p>
        <div><label for="ticket-status" class="mb-1 block text-sm font-bold">Status</label><select id="ticket-status" name="status" class="w-full rounded-xl border border-slate-300 bg-white p-3 dark:border-slate-700 dark:bg-slate-950">@foreach(['open','in_progress','resolved','closed'] as $choice)<option value="{{ $choice }}" @selected($ticket->status===$choice)>{{ ucfirst(str_replace('_',' ',$choice)) }}</option>@endforeach</select></div>
        <div><label for="ticket-priority" class="mb-1 block text-sm font-bold">Priority</label><select id="ticket-priority" name="priority" class="w-full rounded-xl border border-slate-300 bg-white p-3 dark:border-slate-700 dark:bg-slate-950">@foreach(['low','normal','high'] as $choice)<option value="{{ $choice }}" @selected($ticket->priority===$choice)>{{ ucfirst($choice) }}</option>@endforeach</select></div>
        <button type="submit" class="w-full rounded-xl bg-blue-700 px-5 py-3 text-sm font-bold text-white">Save Status</button>
    </form>
    <form method="POST" action="{{ route('admin.support.destroy',['kind'=>$ticket->kind==='chat'?'chats':'tickets','ticket'=>$ticket]) }}" onsubmit="return confirm('Archive this conversation?')" class="rounded-3xl border border-red-200 bg-white p-5 dark:border-red-900 dark:bg-slate-900">@csrf @method('DELETE')<button type="submit" class="text-sm font-bold text-red-700 dark:text-red-400">Archive Conversation</button></form>
@elseif($ticket->kind==='ticket')
    <form method="POST" action="{{ route('support.tickets.update',$ticket) }}" class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">@csrf @method('PATCH')
        <h2 class="font-bold">Edit Ticket</h2>
        <label for="own-subject" class="block text-sm font-bold">Subject</label>
        <input id="own-subject" name="subject" value="{{ old('subject',$ticket->subject) }}" required maxlength="160" class="w-full rounded-xl border border-slate-300 bg-white p-3 dark:border-slate-700 dark:bg-slate-950">
        <label for="own-priority" class="block text-sm font-bold">Priority</label>
        <select id="own-priority" name="priority" class="w-full rounded-xl border border-slate-300 bg-white p-3 dark:border-slate-700 dark:bg-slate-950">@foreach(['low','normal','high'] as $choice)<option value="{{ $choice }}" @selected($ticket->priority===$choice)>{{ ucfirst($choice) }}</option>@endforeach</select>
        <label for="own-status" class="block text-sm font-bold">Status</label>
        <select id="own-status" name="status" class="w-full rounded-xl border border-slate-300 bg-white p-3 dark:border-slate-700 dark:bg-slate-950">@foreach(['open','closed'] as $choice)<option value="{{ $choice }}" @selected($ticket->status===$choice)>{{ ucfirst($choice) }}</option>@endforeach</select>
        <button class="w-full rounded-xl bg-blue-700 px-5 py-3 text-sm font-bold text-white">Save Changes</button>
    </form>
@endif
@if(auth()->id()===$ticket->user_id)
    <form method="POST" action="{{ route('support.tickets.destroy',$ticket) }}" onsubmit="return confirm('Remove this conversation from your account?')" class="rounded-3xl border border-red-200 bg-white p-5 dark:border-red-900 dark:bg-slate-900">@csrf @method('DELETE')
    <button class="text-sm font-bold text-red-700 dark:text-red-400">Remove Conversation</button></form>
@endif
</aside>
</div>
<script>
(() => {
  const feed=document.querySelector('[data-support-feed]');
  if(!feed)return;
  let last=Number(feed.dataset.last || '0');
  let busy=false;
  const endpoint=@json(route('support.messages.index',$ticket));
  async function refresh() {
    if(busy)return; busy=true;
    try {
      const response=await fetch(endpoint+'?after='+last,{credentials:'same-origin',headers:{Accept:'application/json'},cache:'no-store'});
      if(!response.ok)return;
      const result=await response.json();
      for(const msg of result.messages||[]) {
        if(msg.id<=last)continue;
        const card=document.createElement('article');
        card.className='rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-950';
        card.dataset.messageId=String(msg.id);
        const head=document.createElement('div');
        head.className='flex justify-between gap-3 text-xs font-bold text-blue-700 dark:text-blue-400';
        const author=document.createElement('span');author.textContent=msg.author;
        const time=document.createElement('time');time.textContent=msg.at;
        const body=document.createElement('p');
        body.className='mt-2 whitespace-pre-line break-words text-sm leading-7';
        if(typeof msg.body_html==='string' && msg.body_html)body.innerHTML=msg.body_html;
        else body.textContent=msg.body;
        head.append(author,time);card.append(head,body);feed.append(card);last=msg.id;
      }
    } catch(_) { /* Offline: explicit refresh remains usable. */ }
    finally {busy=false;}
  }
  document.querySelector('[data-support-refresh]')?.addEventListener('click',refresh);
  if(!new URLSearchParams(window.location.search).has('page'))window.setInterval(refresh,8000);
})();
</script>
@endsection