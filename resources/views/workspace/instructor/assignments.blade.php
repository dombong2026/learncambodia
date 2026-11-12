@extends('layouts.workspace')
@section('title', 'Homework Builder')
@section('heading', 'Homework Builder')
@section('description', 'Manage homework you authored in your assigned courses. Students see only administrator-approved assignments.')
@section('content')
<div class="mb-5 flex justify-end"><a href="{{ route('instructor.courses.index') }}" class="rounded-full bg-blue-700 px-5 py-3 text-sm font-bold text-white">Choose a teaching course</a></div>
<div class="overflow-x-auto rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
<table class="min-w-[750px] w-full text-left text-sm">
    <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-widest text-slate-500 dark:border-slate-800 dark:bg-slate-900"><tr><th class="px-5 py-4">Title</th><th class="px-5 py-4">Course</th><th class="px-5 py-4">Deadline</th><th class="px-5 py-4">Status</th><th class="px-5 py-4 text-right">Action</th></tr></thead>
    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
    @forelse($assignments as $assignment)
        <tr><td class="px-5 py-4 font-bold">{{ $assignment->title }}</td><td class="px-5 py-4">{{ $assignment->course?->title }}</td><td class="px-5 py-4 text-xs text-slate-500">{{ $assignment->due_at?->format('Y-m-d H:i') ?? 'None' }}</td><td class="px-5 py-4">{{ ucwords(str_replace('_',' ', $assignment->status)) }}</td>
        <td class="px-5 py-4 text-right"><a href="{{ route('instructor.homework.edit',$assignment) }}" class="rounded-full border border-slate-300 px-4 py-2 text-xs font-bold hover:border-blue-400 dark:border-slate-700">Open</a></td></tr>
    @empty
        <tr><td colspan="5" class="px-5 py-12 text-center text-slate-500">No homework yet. Open an assigned course to create your first draft.</td></tr>
    @endforelse
    </tbody>
</table></div>
<div class="mt-6">{{ $assignments->links() }}</div>
@endsection
