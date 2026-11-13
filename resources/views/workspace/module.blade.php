@extends('layouts.workspace')
@section('title', $entry['label'])
@section('heading', $entry['label'])
@section('description', $entry['section_title'])
@section('content')
<div class="max-w-4xl rounded-3xl border border-slate-200 bg-white p-8 shadow-sm dark:border-slate-800 dark:bg-slate-900">
    @if($category)
        <p class="text-xs font-bold uppercase tracking-widest text-blue-700 dark:text-blue-400">Reference category · {{ $category->code }}</p>
        <h2 class="mt-3 text-2xl font-extrabold">{{ $category->name }}</h2>
        <p class="mt-4 leading-8 text-slate-600 dark:text-slate-300">{{ $category->description }}</p>
        <a href="{{ route('admin.categories.edit', $category) }}" class="mt-6 inline-block rounded-full bg-blue-700 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-800">Edit category</a>
    @elseif($level)
        <p class="text-xs font-bold uppercase tracking-widest text-blue-700 dark:text-blue-400">CEFR reference · {{ $level->code }}</p>
        <h2 class="mt-3 text-2xl font-extrabold">{{ $level->name }}</h2>
        <p class="mt-4 leading-8 text-slate-600 dark:text-slate-300">{{ $level->description }}</p>
    @else
        <p class="text-xs font-bold uppercase tracking-widest text-amber-700 dark:text-amber-400">Not yet activated</p>
        <h2 class="mt-3 text-xl font-bold">{{ $entry['section_title'] }} — {{ $entry['label'] }}</h2>
        <p class="mt-4 text-sm leading-8 text-slate-600 dark:text-slate-400">The navigation and role-based access foundation are ready. This module is reserved for a future reviewed implementation. It has no active configuration, external integration, publishing workflow, payment function or customer messaging.</p>
        @if($section === 'finance-manager' || $section === 'advertisement')
            <p class="mt-3 text-sm leading-7 text-slate-500">Financial and advertising integrations will require separate approval and independent security checks.</p>
        @endif
    @endif
</div>
@endsection
