@extends('layouts.workspace')
@section('title', 'Course Manager')
@section('heading', 'Course Manager')
@section('description', 'Create, build and submit real curriculum records. Only independently approved courses may appear in the public catalogue.')
@section('content')
{{-- The shared page heading already provides Create course; avoid a duplicate CTA. --}}
<div class="workspace-panel overflow-x-auto rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <table class="w-full min-w-[760px] text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-widest text-slate-500 dark:border-slate-800 dark:bg-slate-900"><tr><th class="px-5 py-4">Course</th><th class="px-5 py-4">Category</th><th class="px-5 py-4">Level</th><th class="px-5 py-4">Modules</th><th class="px-5 py-4">Status</th><th class="px-5 py-4 text-right">Action</th></tr></thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
        @forelse($courses as $course)
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                <td class="px-5 py-4 font-bold">{{ $course->title }}<p class="mt-1 text-xs font-medium text-slate-500">Edition {{ $course->revision_number }} {{ $course->superseded_at ? '· Superseded' : '' }}</p></td>
                <td class="px-5 py-4 text-slate-500">{{ $course->category?->name }}</td>
                <td class="px-5 py-4">{{ $course->cefr_level ?? 'All' }}</td>
                <td class="px-5 py-4 tabular-nums">{{ $course->modules_count }}</td>
                <td class="px-5 py-4"><span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700 dark:bg-blue-950 dark:text-blue-300">{{ ucwords(str_replace('_',' ', $course->status)) }}</span></td>
                <td class="px-5 py-4 text-right"><a href="{{ route('admin.course-builder.edit', $course) }}" class="rounded-full border border-slate-300 px-4 py-2 text-xs font-bold hover:border-blue-500 dark:border-slate-700">Open builder</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="p-4">
                <x-workspace-empty-state :title="__('ui.empty_courses')" description="No courses yet. Create a draft to start building a curriculum." icon="book-open" :href="route('admin.course-builder.create')" action="Create course" />
            </td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-6">{{ $courses->links() }}</div>
@endsection
