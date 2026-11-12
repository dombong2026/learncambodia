@extends('layouts.workspace')
@section('title', 'Practice Results')
@section('heading', 'Practice Results')
@section('description', 'Your private attempt outcome. Practice scores do not represent official TOEFL, IELTS, CEFR, teaching certifications or other examination results.')
@section('content')
<div class="max-w-5xl space-y-6">
    <div class="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <p class="text-xs font-bold uppercase tracking-widest text-blue-700 dark:text-blue-400">{{ $quiz->title }}</p>
        <div class="mt-4 flex flex-wrap items-end justify-between gap-4">
            <div><p class="text-4xl font-extrabold tabular-nums">{{ $attempt->score_percent }}%</p><p class="mt-2 text-sm text-slate-500">{{ $attempt->correct_count }} of {{ $attempt->question_count }} correct · Attempt {{ $attempt->id }}</p></div>
            <span class="rounded-full px-4 py-2 text-sm font-bold {{ $attempt->passed ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-300' }}">{{ $attempt->passed ? 'Practice threshold met' : 'Keep practising' }}</span>
        </div>
        <p class="mt-4 text-xs leading-6 text-slate-500">Submitted {{ $attempt->submitted_at?->format('Y-m-d H:i') }} · Passing threshold {{ $quiz->passing_percent }}% · No official certificate issued.</p>
        <div class="mt-6 flex flex-wrap gap-3">
            <a href="{{ route('account.practice.show',[$enrollment,$quiz]) }}" class="rounded-full bg-blue-700 px-5 py-3 text-sm font-bold text-white">Practice again</a>
            <a href="{{ route('account.practice.history') }}" class="rounded-full border border-slate-300 px-5 py-3 text-sm font-bold dark:border-slate-700">My results</a>
        </div>
    </div>
    @foreach($attempt->answers as $answer)
        <article class="rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
            <p class="text-xs font-bold uppercase tracking-widest {{ $answer->is_correct ? 'text-emerald-700 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-400' }}">{{ $answer->is_correct ? 'Correct' : 'Needs review' }}</p>
            <h2 class="mt-2 text-base font-bold">{{ $answer->question?->prompt }}</h2>
            @php $selectedField = 'option_'.strtolower($answer->selected_option); @endphp
            <p class="mt-3 text-sm text-slate-600 dark:text-slate-400">Your answer: <strong>{{ $answer->selected_option }}. {{ $answer->question?->$selectedField }}</strong></p>
            @if($answer->question?->explanation)<p class="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-sm leading-7 dark:bg-slate-950">{{ $answer->question->explanation }}</p>@endif
        </article>
    @endforeach
</div>
@endsection
