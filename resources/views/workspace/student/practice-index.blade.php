@extends('layouts.workspace')
@section('title', 'Practice Quizzes')
@section('heading', 'Practice Quizzes')
@section('description', 'Practice the material in your approved courses. These internal quizzes do not issue official exam scores or qualifications.')
@section('content')
<div class="mb-5 flex justify-end"><a href="{{ route('account.practice.history') }}" class="rounded-full border border-slate-300 px-5 py-3 text-sm font-bold dark:border-slate-700">My practice history</a></div>
<div class="space-y-5">
@forelse($enrollments as $enrollment)
    <section class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-xl font-extrabold">{{ $enrollment->course?->title }}</h2>
        <p class="mt-2 text-xs font-semibold text-slate-500">Approved enrollment · practice-only assessments</p>
        <div class="mt-5 grid gap-3 sm:grid-cols-2">
            @forelse($enrollment->course->quizzes as $quiz)
                @if($quiz->questions_count > 0)
                <a href="{{ route('account.practice.show',[$enrollment,$quiz]) }}" class="rounded-2xl border border-slate-200 p-5 transition hover:border-blue-400 dark:border-slate-800">
                    <h3 class="text-base font-bold">{{ $quiz->title }}</h3>
                    <p class="mt-2 text-xs text-slate-500">{{ $quiz->questions_count }} questions · Pass threshold {{ $quiz->passing_percent }}%</p>
                    <span class="mt-4 inline-block text-sm font-bold text-blue-700 dark:text-blue-400">Open practice →</span>
                </a>
                @endif
            @empty
                <p class="text-sm text-slate-500">This course does not have practice quizzes.</p>
            @endforelse
        </div>
    </section>
@empty
    <p class="rounded-3xl border border-slate-200 bg-white p-8 text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-900">Approved course enrollments are needed before taking practice quizzes.</p>
@endforelse
</div>
<div class="mt-7">{{ $enrollments->links() }}</div>
@endsection
