@extends('layouts.workspace')
@section('title', 'Evaluate Student Submission')
@section('heading', 'Evaluate Student Submission')
@section('description', 'Review one student attempt, record feedback, and grade or return the work. Review decisions are audited.')
@section('content')
<a href="{{ route('instructor.grading.index') }}" class="inline-flex rounded-full border border-slate-300 px-5 py-2.5 text-sm font-bold dark:border-slate-700">← Student submissions</a>
<div class="mt-6 max-w-5xl space-y-5">
    <div class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-xs font-bold uppercase tracking-widest text-blue-700 dark:text-blue-400">{{ $submission->assignment?->course?->title }}</p><h2 class="mt-2 text-xl font-extrabold">{{ $submission->assignment?->title }}</h2><p class="mt-2 text-sm text-slate-500">Student: {{ $submission->enrollment?->student?->name }} · Version {{ $submission->version }} · {{ $submission->submitted_at?->format('Y-m-d H:i') }}</p></div><span class="rounded-full bg-blue-50 px-4 py-2 text-xs font-bold text-blue-700 dark:bg-blue-950 dark:text-blue-300">{{ ucfirst($submission->status) }}</span></div>
        <h3 class="mt-6 text-sm font-bold">Current submitted answer</h3>
        <p class="mt-3 whitespace-pre-line break-words rounded-2xl bg-slate-50 p-5 text-sm leading-8 dark:bg-slate-950">{{ $submission->body }}</p>
        @if($submission->assignment?->rubric)<h3 class="mt-6 text-sm font-bold">Approved marking criteria</h3><p class="mt-2 whitespace-pre-line text-sm leading-7 text-slate-600 dark:text-slate-400">{{ $submission->assignment->rubric }}</p>@endif
        @if($submission->status !== 'submitted')
            <p class="mt-5 rounded-2xl border border-slate-200 p-5 text-sm leading-7 dark:border-slate-800"><strong>{{ $submission->status === 'graded' ? 'Recorded score: '.$submission->score.'/100' : 'Returned for revision' }}</strong> · {{ $submission->feedback }}</p>
        @endif
    </div>
    @if($submission->status === 'submitted')
    <div class="grid gap-5 lg:grid-cols-2">
        <form method="POST" action="{{ route('instructor.grading.review',$submission) }}" class="rounded-3xl border border-slate-200 bg-white p-7 dark:border-slate-800 dark:bg-slate-900">@csrf
            <input type="hidden" name="decision" value="grade">
            <h3 class="text-lg font-extrabold">Grade submission</h3>
            <label for="score" class="mt-4 block text-sm font-bold">Score (0–100)</label>
            <input id="score" name="score" type="number" min="0" max="100" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">
            <label for="grading-feedback" class="mt-4 block text-sm font-bold">Instructor feedback</label>
            <textarea id="grading-feedback" name="feedback" rows="5" minlength="5" maxlength="3000" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950"></textarea>
            <div class="mt-5 flex justify-end"><button type="submit" class="rounded-full bg-emerald-700 px-5 py-3 text-sm font-bold text-white">Record grade</button></div>
        </form>
        <form method="POST" action="{{ route('instructor.grading.review',$submission) }}" class="rounded-3xl border border-slate-200 bg-white p-7 dark:border-slate-800 dark:bg-slate-900">@csrf
            <input type="hidden" name="decision" value="return">
            <h3 class="text-lg font-extrabold">Return for revision</h3>
            <p class="mt-2 text-sm leading-7 text-slate-500">The student may resubmit before the approved deadline, up to three versions total.</p>
            <label for="return-feedback" class="mt-4 block text-sm font-bold">Required revision guidance</label>
            <textarea id="return-feedback" name="feedback" rows="5" minlength="5" maxlength="3000" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950"></textarea>
            <div class="mt-5 flex justify-end"><button type="submit" class="rounded-full border border-amber-400 px-5 py-3 text-sm font-bold text-amber-700 dark:text-amber-300">Return assignment</button></div>
        </form>
    </div>
    @endif
    <details class="rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <summary class="cursor-pointer text-sm font-extrabold">Submission version history ({{ $submission->versions->count() }})</summary>
        <div class="mt-4 space-y-4">@foreach($submission->versions as $version)<section class="rounded-xl bg-slate-50 p-5 dark:bg-slate-950"><p class="text-xs font-bold text-slate-500">Version {{ $version->version }} · {{ $version->submitted_at?->format('Y-m-d H:i') }}</p><p class="mt-3 whitespace-pre-line break-words text-sm leading-7">{{ $version->body }}</p></section>@endforeach</div>
    </details>
</div>
@endsection
