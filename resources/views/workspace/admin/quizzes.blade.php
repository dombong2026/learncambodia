@extends('layouts.workspace')
@section('title', 'Practice Quiz Manager')
@section('heading', 'Practice Quiz Manager')
@section('description', 'Review the practice assessments attached to courses. Only draft and rejected course curricula can be edited.')
@section('content')
<div class="mb-5 flex justify-end"><a href="{{ route('admin.courses.index') }}" class="rounded-full bg-blue-700 px-6 py-3 text-sm font-bold text-white">Open Course Manager</a></div>
<div class="overflow-x-auto rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
<table class="min-w-[740px] w-full text-left text-sm">
<thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-widest text-slate-500 dark:border-slate-800 dark:bg-slate-900"><tr><th class="px-5 py-4">Practice quiz</th><th class="px-5 py-4">Course</th><th class="px-5 py-4">Questions</th><th class="px-5 py-4">Pass threshold</th><th class="px-5 py-4 text-right">Action</th></tr></thead>
<tbody class="divide-y divide-slate-100 dark:divide-slate-800">
@forelse($quizzes as $quiz)
<tr><td class="px-5 py-4 font-bold">{{ $quiz->title }}</td><td class="px-5 py-4">{{ $quiz->course?->title }}</td><td class="px-5 py-4 tabular-nums">{{ $quiz->questions_count }}</td><td class="px-5 py-4 tabular-nums">{{ $quiz->passing_percent }}%</td>
<td class="px-5 py-4 text-right"><a href="{{ route('admin.course-builder.quizzes.edit', [$quiz->course_id,$quiz]) }}" class="rounded-full border border-slate-300 px-4 py-2 text-xs font-bold hover:border-blue-500 dark:border-slate-700">Open quiz</a></td></tr>
@empty
<tr><td colspan="5" class="px-5 py-12 text-center text-slate-500">No practice quizzes yet. Add a quiz to a draft course in Course Builder.</td></tr>
@endforelse
</tbody></table></div>
<div class="mt-6">{{ $quizzes->links() }}</div>
@endsection
