@extends('layouts.public')
@section('title', 'Course Categories')
@section('content')
<section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
    <p class="text-sm font-bold uppercase tracking-widest text-blue-700 dark:text-blue-400">Learning directory</p>
    <h1 class="mt-2 text-4xl font-extrabold tracking-tight">{{ \App\Support\Appearance::value('catalog','heading') }}</h1>
    <p class="mt-3 max-w-3xl text-slate-600 dark:text-slate-400">{{ \App\Support\Appearance::value('catalog','intro') }}</p>
    <div class="mt-8 flex flex-wrap gap-2">
        <a href="{{ route('categories.index') }}" class="rounded-full border px-4 py-2 text-sm font-semibold {{ !$group ? 'border-blue-700 bg-blue-700 text-white' : 'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900' }}">All categories</a>
        @foreach (\App\Models\Category::GROUPS as $slug => $name)
            <a href="{{ route('categories.index',['group'=>$slug]) }}" class="rounded-full border px-4 py-2 text-sm font-semibold transition hover:border-blue-400 {{ $group === $slug ? 'border-blue-700 bg-blue-700 text-white' : 'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900' }}">{{ $name }}</a>
        @endforeach
    </div>
    @if($query)
        <p class="mt-6 text-sm text-slate-500">Search results for <strong>{{ $query }}</strong></p>
    @endif
    <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($categories as $category)
            <a href="{{ route('categories.show',$category) }}" class="group flex flex-col rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-lg dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between gap-3"><span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700 dark:bg-blue-950 dark:text-blue-300">{{ $category->code }}</span><span class="text-xs text-slate-500">{{ \App\Models\Category::GROUPS[$category->group] ?? $category->group }}</span></div>
                <h2 class="mt-5 text-xl font-bold group-hover:text-blue-700 dark:group-hover:text-blue-400">{{ $category->name }}</h2>
                <p class="mt-3 flex-1 text-sm leading-7 text-slate-600 dark:text-slate-400">{{ $category->description }}</p>
                <span class="mt-5 text-sm font-semibold text-blue-700 dark:text-blue-400">View category →</span>
            </a>
        @empty
            <p class="col-span-full rounded-2xl border border-dashed border-slate-300 p-8 text-slate-600 dark:border-slate-700 dark:text-slate-300">No published categories match these filters. Try another search.</p>
        @endforelse
    </div>
    <div class="mt-10">{{ $categories->links() }}</div>
    <p class="mt-8 text-xs leading-6 text-slate-500">{{ config('learning.external_assessment_disclaimer') }}</p>
</section>
@endsection
