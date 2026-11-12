@extends('layouts.workspace')
@section('title', 'Learning Reports')
@section('heading', 'Learning Reports')
@section('description', 'Aggregated actual course activity. No individual student practice answers or personal contact details are exposed in these reports.')
@section('content')
<div class="grid gap-4 sm:grid-cols-3">
@foreach($totals as $total)
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900"><p class="text-xs font-bold uppercase tracking-widest text-slate-500">{{ $total['label'] }}</p><p class="mt-4 text-3xl font-extrabold tabular-nums">{{ number_format($total['value']) }}</p></div>
@endforeach
</div>
<div class="mt-7 overflow-x-auto rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <table class="min-w-[960px] w-full text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800 dark:bg-slate-900"><tr><th class="px-5 py-4">Course</th><th class="px-5 py-4">Lessons</th><th class="px-5 py-4">Quizzes</th><th class="px-5 py-4">Approved enrollment</th><th class="px-5 py-4">Pending enrollment</th><th class="px-5 py-4">Lesson completions</th><th class="px-5 py-4">Practice attempts</th></tr></thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
        @forelse($courses as $course)
            <tr><td class="px-5 py-4"><p class="font-bold">{{ $course->title }}</p><p class="mt-1 text-xs text-slate-500">{{ $course->category?->name }}</p></td>
                <td class="px-5 py-4 tabular-nums">{{ $course->lessons_count }}</td>
                <td class="px-5 py-4 tabular-nums">{{ $course->quizzes_count }}</td>
                <td class="px-5 py-4 tabular-nums">{{ $course->approved_enrollments_count }}</td>
                <td class="px-5 py-4 tabular-nums">{{ $course->pending_enrollments_count }}</td>
                <td class="px-5 py-4 tabular-nums">{{ $completedByCourse[$course->id] ?? 0 }}</td>
                <td class="px-5 py-4 tabular-nums">{{ $attemptsByCourse[$course->id] ?? 0 }}</td>
            </tr>
        @empty<tr><td colspan="7" class="px-5 py-12 text-center text-slate-500">No course activity yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-6">{{ $courses->links() }}</div>
<p class="mt-6 text-xs leading-6 text-slate-500">All values represent recorded platform activity, not accreditation, student grades, external exam results or predictions.</p>
@endsection
