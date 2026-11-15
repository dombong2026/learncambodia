@extends('layouts.workspace')
@section('title','Create Support Ticket')
@section('heading','Create Ticket')
@section('description','Describe your support request. Messages stay private and no external notifications are sent.')
@section('heading_action')
<button type="submit" form="support-create-form" class="rounded-xl bg-blue-700 px-5 py-3 text-sm font-bold text-white">Create Ticket</button>
<a href="{{ route('support.tickets.index') }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-bold">All Tickets</a>
@endsection
@section('content')
<form id="support-create-form" method="POST" action="{{ route('support.tickets.store') }}" class="max-w-3xl space-y-5 rounded-3xl border border-slate-200 bg-white p-7 dark:border-slate-700 dark:bg-slate-900">@csrf
<div><label for="support-subject" class="mb-2 block text-sm font-bold">Subject</label><input id="support-subject" name="subject" maxlength="160" minlength="3" required value="{{ old('subject') }}" class="w-full rounded-xl border border-slate-300 bg-white p-3 text-sm dark:border-slate-700 dark:bg-slate-950">@error('subject')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
<div><label for="support-priority" class="mb-2 block text-sm font-bold">Priority</label><select id="support-priority" name="priority" class="w-full rounded-xl border border-slate-300 bg-white p-3 text-sm dark:border-slate-700 dark:bg-slate-950">@foreach(['low','normal','high'] as $p)<option value="{{ $p }}" @selected(old('priority','normal')===$p)>{{ ucfirst($p) }}</option>@endforeach</select></div>
<div><label for="support-message" class="mb-2 block text-sm font-bold">Your message</label><textarea id="support-message" name="body" rows="7" required minlength="3" maxlength="4000" class="w-full rounded-xl border border-slate-300 bg-white p-3 text-sm dark:border-slate-700 dark:bg-slate-950">{{ old('body') }}</textarea>@error('body')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
<button type="submit" class="rounded-xl bg-blue-700 px-6 py-3 text-sm font-bold text-white">Create Ticket</button>
</form>
@endsection