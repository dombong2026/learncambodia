@extends('layouts.workspace')
@section('title', 'Practice History')
@section('heading', 'My Practice History')
@section('description', 'Your recorded, private practice assessments. A passing practice score does not confer a formal qualification.')
@section('content')
<div class="mb-5 flex justify-end"><a href="{{ route('account.practice.index') }}" class="rounded-full bg-blue-700 px-5 py-3 text-sm font-bold text-white">Browse practice quizzes</a></div>
<div class="overflow-x-auto rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <table class="min-w-[710px] w-full text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-widest text-slate-500 dark:border-slate-800 dark:bg-slate-900"><tr><th class="px-5 py-4">Quiz</th><th class="px-5 py-4">Course</th><th class="px-5 py-4">Score</th><th class="px-5 py-4">Submitted</th><th class="px-5 py-4 text-right">Action</th></tr></thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
        @forelse($attempts as $attempt)
            <tr><td class="px-5 py-4 font-bold">{{ $attempt->quiz?->title }}</td><td class="px-5 py-4">{{ $attempt->quiz?->course?->title }}</td><td class="px-5 py-4 font-bold tabular-nums">{{ $attempt->score_percent }}%</td><td class="px-5 py-4 text-xs">{{ $attempt->submitted_at?->format('Y-m-d') }}</td>
            <td class="px-5 py-4 text-right"><a href="{{ route('account.practice.result',[$attempt->enrollment_id,$attempt->course_quiz_id,$attempt]) }}" class="rounded-full border border-slate-300 px-4 py-2 text-xs font-bold hover:border-blue-400 dark:border-slate-700">Review result</a></td></tr>
        @empty
            <tr><td colspan="5" class="px-5 py-12 text-center text-slate-500">No practice attempts recorded yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-6">{{ $attempts->links() }}</div>
@endsection
