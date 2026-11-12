@extends('layouts.workspace')
@section('title', 'CEFR Reference')
@section('heading', 'CEFR Level Guide')
@section('description', 'Use these six reference descriptors for planning, not as an official assessment result.')
@section('content')
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
    @foreach($levels as $level)
    <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <p class="text-3xl font-black text-blue-700 dark:text-blue-400">{{ $level->code }}</p>
        <h2 class="mt-3 text-lg font-bold">{{ $level->name }}</h2>
        <p class="mt-1 text-xs font-semibold text-slate-500">{{ $level->category }}</p>
        <p class="mt-4 text-sm leading-7 text-slate-600 dark:text-slate-400">{{ $level->description }}</p>
    </article>
    @endforeach
</div>
@endsection
