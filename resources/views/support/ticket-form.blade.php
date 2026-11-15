@extends('layouts.workspace')
@section('title','Create Support Ticket')
@section('heading','Create Ticket')
@section('description','Send a private support request. Media attachments remain access-controlled; external notifications are disabled.')
@section('heading_action')
<button type="submit" form="support-create-form" class="rounded-lg bg-blue-700 px-4 py-2.5 text-xs font-bold text-white">Create ticket</button>
<a href="{{ route('support.tickets.index') }}" class="rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-bold dark:border-slate-700">All tickets</a>
@endsection
@section('content')
<form id="support-create-form" method="POST" action="{{ route('support.tickets.store') }}" class="grid min-w-0 gap-5 xl:grid-cols-[minmax(0,1fr)_318px]">
    @csrf
    <section class="min-w-0 space-y-4 rounded-2xl border border-slate-200 p-4 sm:p-5 dark:border-slate-700">
        <h2 class="text-sm font-extrabold">Your message</h2>
        <x-rich-editor id="rich-ticket-body" :value="old('body','')" :html="old('body_html')" context="ticket" :max-length="4000"/>
        <div class="flex justify-end"><button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-xs font-bold text-white">Create ticket</button></div>
    </section>
    <aside class="space-y-4 xl:sticky xl:top-24 xl:self-start" aria-label="Ticket settings">
        <div class="space-y-4 rounded-2xl border border-slate-200 p-4 dark:border-slate-700">
            <h2 class="text-sm font-extrabold">Ticket settings</h2>
            <div><label for="support-subject" class="mb-1 block text-xs font-semibold">Subject</label>
                <input id="support-subject" name="subject" value="{{ old('subject') }}" required minlength="3" maxlength="160" class="w-full rounded-lg border border-slate-300 bg-transparent px-3 py-2 text-sm dark:border-slate-700">
                @error('subject')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div><label for="support-priority" class="mb-1 block text-xs font-semibold">Priority</label>
                <select id="support-priority" name="priority" class="w-full rounded-lg border border-slate-300 bg-transparent px-3 py-2 text-sm dark:border-slate-700">
                    @foreach(['low','normal','high'] as $priority)<option value="{{ $priority }}" @selected(old('priority','normal')===$priority)>{{ ucfirst($priority) }}</option>@endforeach
                </select>
            </div>
            <p data-editor-words-summary class="text-xs text-slate-500">Message word count updates as you write.</p>
            <p class="border-t border-slate-200 pt-3 text-xs leading-6 text-slate-500 dark:border-slate-700">New tickets are private and start with Open status. Only your account and support administrators can read them.</p>
        </div>
        <nav class="space-y-2 rounded-2xl border border-slate-200 p-4 text-xs dark:border-slate-700" aria-label="Support shortcuts">
            <h2 class="mb-2 text-sm font-extrabold">Support resources</h2>
            <a href="{{ route('support.tickets.index') }}" class="block text-blue-600 underline">My tickets</a>
            <a href="{{ route('help.index') }}" class="block text-blue-600 underline">Help Center</a>
        </nav>
    </aside>
</form>
@endsection
