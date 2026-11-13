@extends('layouts.workspace')
@section('title', $quiz->title)
@section('heading', $quiz->title)
@section('description', 'Private, graded learning exercise. Your responses are checked on the server; this is not an official exam or certificate.')
@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <a href="{{ route('account.practice.index') }}" class="rounded-full border border-slate-300 px-5 py-2.5 text-sm font-bold dark:border-slate-700">← Back to practice quizzes</a>
    <span class="rounded-full bg-blue-50 px-4 py-2 text-xs font-bold text-blue-700 dark:bg-blue-950 dark:text-blue-300">{{ $attemptsRemaining }} of 5 attempts remaining</span>
</div>
@if($quiz->instructions)<p class="mb-5 max-w-4xl rounded-2xl border border-slate-200 bg-white p-5 text-sm leading-7 dark:border-slate-800 dark:bg-slate-900">{{ $quiz->instructions }}</p>@endif
@if($attemptsRemaining === 0)
    <div class="rounded-3xl border border-amber-200 bg-amber-50 p-8 text-sm font-semibold text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300">You have reached the five-attempt limit for this practice quiz. Your previous results remain available in Practice History.</div>
@else
<form method="POST" action="{{ route('account.practice.store',[$enrollment,$quiz]) }}" class="max-w-5xl space-y-5">@csrf
    @foreach($questions as $question)
        <fieldset class="rounded-3xl border border-slate-200 bg-white p-7 dark:border-slate-800 dark:bg-slate-900">
            <legend class="px-1 text-sm font-bold text-blue-700 dark:text-blue-400">Question {{ $loop->iteration }} of {{ $questions->count() }}</legend>
            <p class="mt-1 whitespace-pre-line text-base font-bold leading-7">{{ $question->prompt }}</p>
            <div class="mt-5 space-y-2">
                @foreach(['A','B','C','D'] as $letter)
                    @php $field = 'option_'.strtolower($letter); @endphp
                    <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-200 px-4 py-3 text-sm transition hover:border-blue-400 dark:border-slate-800">
                        <input type="radio" name="answers[{{ $question->id }}]" value="{{ $letter }}" @checked(old('answers.'.$question->id)===$letter) required class="mt-1 shrink-0 accent-blue-700">
                        <span><strong>{{ $letter }}.</strong> {{ $question->$field }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>
    @endforeach
    <div class="flex justify-end"><button type="submit" class="rounded-full bg-blue-700 px-7 py-3 text-sm font-bold text-white hover:bg-blue-800">Submit practice answers</button></div>
</form>
@endif
@endsection
