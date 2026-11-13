@extends('layouts.workspace')
@section('title','Contact Inquiries')
@section('heading','Contact Inquiries')
@section('description','Private inquiries can only be read and reviewed by authorized administrators. No external notifications are sent.')
@section('content')
<form action="{{ route('admin.contact-inquiries.index') }}" method="GET" class="mb-6 flex flex-wrap items-end justify-end gap-3">
    <div><label for="inquiry-filter" class="mb-1 block text-xs font-bold">Status</label><select id="inquiry-filter" name="status" class="rounded-xl border border-slate-300 bg-white px-4 py-3 text-xs dark:border-slate-700 dark:bg-[#11284a]"><option value="">All</option>@foreach(['new','reviewed','closed'] as $choice)<option value="{{ $choice }}" @selected($status===$choice)>{{ ucfirst($choice) }}</option>@endforeach</select></div>
    <button type="submit" class="rounded-xl bg-[#12345f] px-5 py-3 text-xs font-bold text-white">Apply</button>
</form>
<div class="space-y-4">
@forelse($inquiries as $inquiry)
    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-[#152e4d]">
        <div class="flex flex-wrap items-start justify-between gap-4"><div>
            <p class="text-xs font-bold uppercase tracking-wider text-[#b17b27]">{{ ucfirst($inquiry->topic) }} · #{{ $inquiry->id }}</p>
            <h2 class="mt-2 break-words text-xl font-black">{{ $inquiry->subject }}</h2>
            <p class="mt-2 text-xs text-slate-500">{{ $inquiry->name }} · <a href="mailto:{{ $inquiry->email }}" class="font-bold text-[#a97b2b] hover:underline">{{ $inquiry->email }}</a> · {{ $inquiry->created_at->format('Y-m-d H:i') }}</p>
        </div><span class="rounded-full bg-[#f7eacb] px-3 py-1.5 text-xs font-bold text-[#80551b]">{{ ucfirst($inquiry->status) }}</span></div>
        <div class="mt-5 whitespace-pre-line break-words rounded-2xl bg-slate-50 p-5 text-sm leading-8 text-slate-700 dark:bg-[#0d2240] dark:text-slate-300">{{ $inquiry->message }}</div>
        <form method="POST" action="{{ route('admin.contact-inquiries.update',$inquiry) }}" class="mt-5 flex flex-wrap items-end gap-3">@csrf @method('PATCH')
            <div class="min-w-[180px] flex-1"><label for="review-note-{{ $inquiry->id }}" class="mb-1 block text-xs font-bold">Private review note</label><input id="review-note-{{ $inquiry->id }}" name="review_note" maxlength="500" value="{{ $inquiry->review_note }}" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-xs dark:border-slate-700 dark:bg-[#0d2240]"></div>
            <button type="submit" name="status" value="reviewed" class="rounded-xl border border-[#d5aa61] px-5 py-3 text-xs font-bold text-[#b17b27]">Mark reviewed</button>
            <button type="submit" name="status" value="closed" class="rounded-xl bg-[#12365f] px-5 py-3 text-xs font-black text-white">Close</button>
        </form>
    </section>
@empty
    <p class="rounded-2xl border border-dashed border-slate-300 bg-white p-9 text-sm text-slate-500 dark:border-slate-700 dark:bg-[#152e4d]">No inquiries match this filter.</p>
@endforelse
</div>
<div class="mt-6">{{ $inquiries->links() }}</div>
@endsection