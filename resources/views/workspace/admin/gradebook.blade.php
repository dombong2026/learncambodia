@extends('layouts.workspace')
@section('title', 'Assignment Gradebook')
@section('heading', 'Assignment Gradebook')
@section('description', 'Private administrator oversight of actual homework evaluations. Individual scores are internal learning results, not official examinations or awards.')
@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <p class="text-sm leading-7 text-slate-500">Instructor feedback and student answer histories remain available only to authorized teaching staff and the student. This table is for oversight, not for changing grades.</p>
    <a href="{{ route('admin.academic.evidence') }}" class="rounded-full border border-blue-300 px-5 py-2.5 text-sm font-bold text-blue-700 dark:border-blue-900 dark:text-blue-400">Academic evidence review</a>
</div>
<div class="overflow-x-auto rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
<table class="min-w-[970px] w-full text-left text-sm">
    <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-widest text-slate-500 dark:border-slate-800 dark:bg-slate-900">
        <tr><th class="px-5 py-4">Student</th><th class="px-5 py-4">Course</th><th class="px-5 py-4">Assignment</th><th class="px-5 py-4">Version</th><th class="px-5 py-4">Status</th><th class="px-5 py-4">Score</th><th class="px-5 py-4">Submitted</th></tr>
    </thead>
    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
    @forelse($submissions as $submission)
        <tr>
            <td class="px-5 py-4 font-bold">{{ $submission->enrollment?->student?->name }}</td>
            <td class="px-5 py-4">{{ $submission->assignment?->course?->title }}</td>
            <td class="px-5 py-4">{{ $submission->assignment?->title }}</td>
            <td class="px-5 py-4 tabular-nums">{{ $submission->version }}</td>
            <td class="px-5 py-4 font-semibold">{{ ucfirst($submission->status) }}</td>
            <td class="px-5 py-4 tabular-nums">{{ $submission->status === 'graded' ? $submission->score.'/100' : '—' }}</td>
            <td class="px-5 py-4 text-xs text-slate-500">{{ $submission->submitted_at?->format('Y-m-d') }}</td>
        </tr>
    @empty<tr><td colspan="7" class="px-5 py-12 text-center text-slate-500">No instructor-graded assignments exist yet.</td></tr>@endforelse
    </tbody>
</table>
</div>
<div class="mt-6">{{ $submissions->links() }}</div>
@endsection
