@extends('layouts.workspace')
@section('title', 'Appearance Management')
@section('heading', 'Appearance Management')
@section('description', 'Prepare and review independent website appearance revisions. Public appearance changes activate only after a different administrator approves them.')
@section('content')
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
    @foreach($sections as $section)
        <a href="{{ route('admin.appearance.edit', $section['key']) }}" class="group rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-400 dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-lg font-extrabold capitalize group-hover:text-blue-700 dark:group-hover:text-blue-400">{{ str_replace('-', ' ', $section['key']) }}</h2>
                @if($section['pending'])<span class="rounded-full bg-amber-50 px-3 py-1 text-[11px] font-bold text-amber-800 dark:bg-amber-950 dark:text-amber-300">Under review</span>
                @elseif($section['approved'])<span class="rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-bold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">Active</span>
                @else<span class="rounded-full bg-slate-100 px-3 py-1 text-[11px] font-bold text-slate-500 dark:bg-slate-800">Default</span>@endif
            </div>
            <p class="mt-4 text-sm leading-7 text-slate-500 dark:text-slate-400">Edit safely constrained text and presentation settings for the {{ $section['key'] }} area.</p>
            <span class="mt-6 inline-block text-sm font-bold text-blue-700 dark:text-blue-400">Manage appearance →</span>
        </a>
    @endforeach
</div>
<div class="mt-8 flex justify-end">
    <a href="{{ route('admin.reviews.index', ['focus' => 'appearance']) }}" class="rounded-full border border-slate-300 px-6 py-3 text-sm font-semibold hover:border-blue-400 dark:border-slate-700">Open appearance review queue</a>
</div>
@endsection
