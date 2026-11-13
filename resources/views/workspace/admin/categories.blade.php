@extends('layouts.workspace')
@section('title', 'Categories')
@section('heading', 'Category Manager')
@section('description', 'Manage the educational category catalogue, its descriptions, publication state and display order.')
@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-4">
    <form method="GET" action="{{ route('admin.categories.index') }}" role="search" class="flex min-w-0 flex-1 gap-2">
        <label for="category-query" class="sr-only">Search categories</label>
        <input id="category-query" type="search" name="q" value="{{ $query }}" maxlength="80" placeholder="Search code or category…" class="w-full max-w-md rounded-full border border-slate-300 bg-white px-4 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-900">
        <button type="submit" class="rounded-full border border-slate-300 px-5 py-2.5 text-sm font-semibold dark:border-slate-700">Search</button>
    </form>
    <a href="{{ route('admin.categories.create') }}" class="rounded-full bg-blue-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-blue-800">Create Category</a>
</div>
<div class="overflow-x-auto rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <table class="min-w-[780px] w-full text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-widest text-slate-500 dark:border-slate-800 dark:bg-slate-900">
            <tr><th scope="col" class="px-5 py-4">Code</th><th scope="col" class="px-5 py-4">Category</th><th scope="col" class="px-5 py-4">Group</th><th scope="col" class="px-5 py-4">Visibility</th><th scope="col" class="px-5 py-4">Order</th><th scope="col" class="px-5 py-4 text-right">Actions</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
        @forelse($categories as $category)
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                <td class="px-5 py-4 font-extrabold text-blue-700 dark:text-blue-400">{{ $category->code }}</td>
                <td class="px-5 py-4"><p class="font-semibold">{{ $category->name }}</p><p class="mt-1 line-clamp-1 max-w-xs text-xs text-slate-500">{{ $category->description }}</p></td>
                <td class="px-5 py-4 text-slate-500">{{ \App\Models\Category::GROUPS[$category->group] ?? $category->group }}</td>
                <td class="px-5 py-4"><span class="rounded-full px-3 py-1 text-xs font-bold {{ $category->is_published ? 'bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300' }}">{{ $category->is_published ? 'Published' : 'Unpublished' }}</span></td>
                <td class="px-5 py-4 tabular-nums">{{ $category->sort_order }}</td>
                <td class="px-5 py-4 text-right"><a href="{{ route('admin.categories.edit', $category) }}" class="rounded-full border border-slate-300 px-4 py-2 text-xs font-bold hover:border-blue-500 dark:border-slate-700">Edit</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-5 py-12 text-center text-slate-500">No category records match the search.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-6">{{ $categories->links() }}</div>
@endsection
