@extends('layouts.public')
@section('title', 'Guest assessment')
@section('content')
<div class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
    <a class="text-sm font-semibold underline" href="{{ $exam->publicUrl() }}">Return to assessment</a>
    <div class="my-5 rounded-2xl border bg-white p-5 dark:bg-slate-900">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Guest assessment</p>
        <h1 class="mt-2 text-2xl font-extrabold">{{ $attempt->snapshot['title'] }}</h1>
        <p class="mt-2 text-sm">Keep this browser session to view your attempt and results. No learner account is created.</p>
    </div>
    @if($errors->any())
        <ul role="alert" class="mb-4 list-disc pl-5 text-red-600">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    @endif
    @if($attempt->submitted_at)
        <section class="rounded-2xl border bg-white p-6 dark:bg-slate-900">
            <h2 class="text-xl font-extrabold">{{ $attempt->passed ? 'Passed' : 'Attempt complete' }}</h2>
            @if($attempt->score !== null)
                <p>{{ $attempt->score }}% scored · {{ $attempt->snapshot['passing_percent'] }}% required</p>
            @elseif(collect($attempt->snapshot['questions'])->contains(fn ($q) => \App\Support\AssessmentQuestions::manual($q)))
                <p>Waiting for writing grades.</p>
            @else
                <p>Your responses have been recorded.</p>
            @endif
            @foreach($attempt->grading ?? [] as $index => $grade)
                @if(!empty($grade['feedback']))<p class="mt-3 break-words">{{ $index + 1 }}. {{ $grade['feedback'] }}</p>@endif
            @endforeach
        </section>
    @elseif(!$attempt->expires_at->isFuture())
        <p class="rounded-2xl border p-6">This attempt ended when the time limit expired.</p>
    @else
        <section class="rounded-2xl border bg-white p-5 dark:bg-slate-900">
            <h2 class="font-bold">Instructions</h2>
            <p class="mt-2 whitespace-pre-wrap break-words">{{ html_entity_decode(strip_tags($attempt->snapshot['instructions_html'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8') }}</p>
            <p class="mt-3">Time remaining: <output data-exam-countdown data-expires="{{ $attempt->expires_at->getTimestamp() }}" class="font-bold tabular-nums"></output></p>
        </section>
        <form data-exam-attempt method="post" action="{{ route('guest-assessments.submit', $attempt) }}" class="mt-5 space-y-4">
            @csrf
            @foreach($attempt->snapshot['questions'] as $index => $question)
                @include('workspace.student.assessment-question', ['guestAttempt' => true])
            @endforeach
            <button type="submit" class="rounded-xl bg-blue-700 px-5 py-3 font-bold text-white">Submit attempt</button>
        </form>
    @endif
</div>
@endsection
