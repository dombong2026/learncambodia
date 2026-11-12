@extends('layouts.workspace')
@section('title', 'My Submitted Assignments')
@section('heading', 'My Submitted Assignments')
@section('description', 'Your own assignment attempts, grading status and feedback. Student work is not shared with classmates.')
@section('content')
<div class="mb-5 flex justify-end"><a href="{{ route('account.homework.index') }}" class="rounded-full bg-blue-700 px-5 py-3 text-sm font-bold text-white">Browse assignments</a></div>
<div class="overflow-x-auto rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
<table class="min-w-[760px] w-full text-left text-sm">
    <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-widest text-slate-500 dark:border-slate-800 dark:bg-slate-900"><tr><th class="px-5 py-4">Assignment</th><th class="px-5 py-4">Course</th><th class="px-5 py-4">Version</th><th class="px-5 py-4">Status</th><th class="px-5 py-4">Grade</th><th class="px-5 py-4 text-right">Action</th></tr></thead>
    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
    @forelse($submissions as $submission)
        <tr><td class="px-5 py-4 font-bold">{{ $submission->assignment?->title }}</td><td class="px-5 py-4">{{ $submission->assignment?->course?->title }}</td><td class="px-5 py-4 tabular-nums">{{ $submission->version }}</td><td class="px-5 py-4">{{ ucfirst($submission->status) }}</td><td class="px-5 py-4">{{ $submission->status==='graded' ? $submission->score.'/100' : '—' }}</td><td class="px-5 py-4 text-right"><a href="{{ route('account.homework.result',$submission) }}" class="rounded-full border border-slate-300 px-4 py-2 text-xs font-bold hover:border-blue-500 dark:border-slate-700">View</a></td></tr>
    @empty
        <tr><td colspan="6" class="px-5 py-12 text-center text-slate-500">No assignments submitted yet.</td></tr>
    @endforelse
    </tbody>
</table></div>
<div class="mt-6">{{ $submissions->links() }}</div>
@endsection
