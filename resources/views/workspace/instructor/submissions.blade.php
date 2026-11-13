@extends('layouts.workspace')
@section('title', 'Student Assignment Submissions')
@section('heading', 'Student Assignment Submissions')
@section('description', 'Review only work in courses you are currently authorized to teach. Grading is out of 100 with written academic feedback.')
@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <a href="{{ route('instructor.feedback-templates.index') }}" class="rounded-xl border border-blue-400 px-5 py-3 text-sm font-bold text-blue-700 dark:text-blue-300">Manage feedback templates</a>
    <a href="{{ route('instructor.insights.index') }}" class="text-sm font-bold text-blue-700 dark:text-blue-300">Teaching insights</a>
</div>
<form method="GET" action="{{ route('instructor.grading.index') }}" class="mb-5 flex flex-wrap gap-3 rounded-2xl bg-white p-4 dark:bg-slate-900">
    <select name="course" aria-label="Filter assigned course" class="min-w-0 flex-1 rounded-xl border p-3 dark:bg-slate-950">
        <option value="">All assigned courses</option>
        @foreach($courses as $course)<option value="{{ $course->id }}" @selected(($filters['course']??'')==$course->id)>{{ $course->title }}</option>@endforeach
    </select>
    <select name="status" aria-label="Filter submission status" class="rounded-xl border p-3 dark:bg-slate-950">
        <option value="">All statuses</option>
        @foreach(['submitted','graded','returned'] as $status)<option value="{{ $status }}" @selected(($filters['status']??'')===$status)>{{ ucfirst($status) }}</option>@endforeach
    </select>
    <button class="rounded-xl bg-blue-700 px-5 py-3 text-sm font-bold text-white">Filter</button>
</form>
<div class="overflow-x-auto rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <table class="min-w-[900px] w-full text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-widest text-slate-500 dark:border-slate-800 dark:bg-slate-900"><tr><th class="px-5 py-4">Student</th><th class="px-5 py-4">Assignment</th><th class="px-5 py-4">Course</th><th class="px-5 py-4">Version</th><th class="px-5 py-4">Status</th><th class="px-5 py-4 text-right">Action</th></tr></thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
        @forelse($submissions as $submission)
            <tr><td class="px-5 py-4 font-bold">{{ $submission->enrollment?->student?->name }}</td>
                <td class="px-5 py-4">{{ $submission->assignment?->title }}</td>
                <td class="px-5 py-4 text-slate-500">{{ $submission->assignment?->course?->title }}</td>
                <td class="px-5 py-4 tabular-nums">{{ $submission->version }}</td>
                <td class="px-5 py-4 font-semibold">{{ ucfirst($submission->status) }}</td>
                <td class="px-5 py-4 text-right"><a href="{{ route('instructor.grading.show',$submission) }}" class="rounded-full border border-slate-300 px-4 py-2 text-xs font-bold hover:border-blue-500 dark:border-slate-700">Review</a></td></tr>
        @empty<tr><td colspan="6" class="px-5 py-12 text-center text-slate-500">No work submitted in your assigned courses yet.</td></tr>@endforelse
        </tbody>
    </table>
</div><div class="mt-6">{{ $submissions->links() }}</div>
@endsection
