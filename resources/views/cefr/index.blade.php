@extends('layouts.public')
@section('title', 'CEFR English Levels A1–C2')
@section('content')
<section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
    <p class="text-sm font-bold uppercase tracking-widest text-blue-700 dark:text-blue-400">Proficiency framework</p>
    <h1 class="mt-2 text-4xl font-extrabold tracking-tight">Understand your English level</h1>
    <p class="mt-4 max-w-3xl text-base leading-8 text-slate-600 dark:text-slate-400">The Common European Framework of Reference for Languages (CEFR) describes proficiency from A1 to C2. These descriptors are a learning guide, not a replacement for validated testing.</p>
    <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($levels as $level)
        <article id="{{ $level->code }}" class="scroll-mt-48 rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between"><span class="text-3xl font-black text-blue-700 dark:text-blue-400">{{ $level->code }}</span><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $level->category }}</span></div>
            <h2 class="mt-5 text-xl font-extrabold">{{ $level->name }}</h2>
            <p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-400">{{ $level->description }}</p>
        </article>
        @endforeach
    </div>
    <div class="mt-10 rounded-3xl border border-blue-200 bg-blue-50 p-6 text-sm leading-7 text-blue-950 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-100">
        <strong>Assessment note:</strong> A CEFR descriptor helps guide study choices. Official test scores, certificate awards and level equivalences must come from the relevant authorized assessment provider.
    </div>
</section>
@endsection
