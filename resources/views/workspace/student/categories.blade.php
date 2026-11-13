@extends('layouts.workspace')
@section('title', 'Course Catalog')
@section('heading', 'Learning Categories')
@section('description', 'Browse approved English-language learning categories. Courses are only listed publicly after review.')
@section('content')
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
    @forelse($categories as $category)
        <a href="{{ route('categories.show', $category) }}" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:border-blue-400 dark:border-slate-800 dark:bg-slate-900">
            <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-extrabold text-blue-700 dark:bg-blue-950 dark:text-blue-300">{{ $category->code }}</span>
            <h2 class="mt-4 text-lg font-bold">{{ $category->name }}</h2>
            <p class="mt-3 line-clamp-3 text-sm leading-7 text-slate-600 dark:text-slate-400">{{ $category->description }}</p>
            <span class="mt-5 inline-block text-sm font-bold text-blue-700 dark:text-blue-400">View pathway →</span>
        </a>
    @empty
        <p class="text-sm text-slate-500">No learning categories have been approved for publication.</p>
    @endforelse
</div>
<div class="mt-7">{{ $categories->links() }}</div>
@endsection
