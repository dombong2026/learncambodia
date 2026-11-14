@extends('layouts.workspace')
@section('title', ucfirst($kind) . ' Manager')
@section('heading', ucfirst($kind) . ' Manager')
@section('description', 'Manage controlled editorial drafts. Articles require approval by another administrator before publication; policies and pages remain private drafts.')
@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-4">
    <form method="GET" action="{{ route('admin.editorial.index', ['kind' => $kind]) }}" class="flex min-w-0 flex-1 gap-2" role="search">
        <label for="editorial-query" class="sr-only">Search {{ $kind }}</label>
        <input id="editorial-query" type="search" name="q" maxlength="80" value="{{ $query }}" placeholder="Search draft titles…" class="w-full max-w-md rounded-full border border-slate-300 bg-white px-4 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-900">
        <button type="submit" class="rounded-full border border-slate-300 px-5 py-2.5 text-sm font-semibold dark:border-slate-700">Search</button>
    </form>
    @if($kind==='posts')<a href="{{ route('admin.editorial.reviews',['kind'=>'posts']) }}" class="rounded-full border border-blue-400 px-5 py-2.5 text-sm font-bold text-blue-700 dark:text-blue-400">Review queue</a>@endif
    <a href="{{ route('admin.editorial.create', ['kind' => $kind]) }}" class="rounded-full bg-blue-700 px-6 py-2.5 text-sm font-bold text-white hover:bg-blue-800">New draft</a>
</div>
<div class="workspace-panel overflow-x-auto rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <table class="w-full min-w-[650px] text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-widest text-slate-500 dark:border-slate-800 dark:bg-slate-900">
            <tr><th scope="col" class="px-5 py-4">Title</th><th scope="col" class="px-5 py-4">Slug</th><th scope="col" class="px-5 py-4">Status</th><th scope="col" class="px-5 py-4">Updated</th><th scope="col" class="px-5 py-4 text-right">Actions</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
        @forelse($entries as $entry)
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                <td class="px-5 py-4 font-bold">{{ $entry->title }}</td>
                <td class="px-5 py-4 font-mono text-xs text-slate-500">{{ $entry->slug }}</td>
                <td class="px-5 py-4"><span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-800 dark:bg-amber-950 dark:text-amber-300">{{ ucfirst($entry->status) }}</span></td>
                <td class="px-5 py-4 text-xs text-slate-500">{{ $entry->updated_at?->format('Y-m-d') }}</td>
                <td class="px-5 py-4 text-right"><a href="{{ route('admin.editorial.edit', ['kind' => $kind, 'entry' => $entry]) }}" class="rounded-full border border-slate-300 px-4 py-2 text-xs font-semibold hover:border-blue-500 dark:border-slate-700">{{ in_array($entry->status,['draft','rejected'],true) ? 'Edit draft' : 'View locked entry' }}</a></td>
            </tr>
        @empty
            <tr><td colspan="5" class="p-4">
                <x-workspace-empty-state :title="__('ui.empty_editorial')" :description="'No '.$kind.' drafts have been created.'" icon="file" :href="route('admin.editorial.create', ['kind' => $kind])" action="New draft" />
            </td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-6">{{ $entries->links() }}</div>
@endsection
