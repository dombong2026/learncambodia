@extends('layouts.workspace')
@section('title', 'Assignment Feedback')
@section('heading', 'Assignment Feedback')
@section('description', 'Private instructor feedback and an immutable history of your submitted answer versions. Scores are internal learning grades, not formal certificates.')
@section('content')
<a href="{{ route('account.homework.submissions') }}" class="inline-flex rounded-full border border-slate-300 px-5 py-2.5 text-sm font-bold dark:border-slate-700">← Submitted work</a>
<div class="mt-6 max-w-5xl space-y-5">
    <section class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <p class="text-xs font-bold uppercase tracking-widest text-blue-700 dark:text-blue-400">{{ $submission->assignment?->course?->title }}</p>
        <h2 class="mt-2 text-xl font-extrabold">{{ $submission->assignment?->title }}</h2>
        <p class="mt-2 text-sm text-slate-500">Version {{ $submission->version }} · Submitted {{ $submission->submitted_at?->format('Y-m-d H:i') }}</p>
        <p class="mt-5 text-sm font-extrabold">{{ ucfirst($submission->status) }} @if($submission->status==='graded') · {{ $submission->score }}/100 @endif</p>
        @if($submission->feedback)<p class="mt-4 rounded-2xl bg-blue-50 p-5 text-sm leading-7 text-blue-900 dark:bg-blue-950 dark:text-blue-200"><strong>Instructor feedback:</strong> {{ $submission->feedback }}</p>@endif
        <p class="mt-5 whitespace-pre-line break-words text-sm leading-8 text-slate-600 dark:text-slate-300">{{ $submission->body }}</p>
        @if($submission->status==='returned')
            <div class="mt-5"><a href="{{ route('account.homework.show',$submission->assignment) }}" class="rounded-full bg-blue-700 px-5 py-3 text-sm font-bold text-white">Review revision instructions</a></div>
        @endif
    </section>
    <details class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <summary class="cursor-pointer text-sm font-extrabold">Previous submission versions ({{ $submission->versions->count() }})</summary>
        <div class="mt-5 space-y-4">@foreach($submission->versions as $version)<div class="rounded-2xl bg-slate-50 p-5 dark:bg-slate-950"><p class="text-xs font-bold text-slate-500">Version {{ $version->version }} · {{ $version->submitted_at?->format('Y-m-d H:i') }}</p><p class="mt-3 whitespace-pre-line break-words text-sm leading-7">{{ $version->body }}</p></div>@endforeach</div>
    </details>
</div>
@endsection
