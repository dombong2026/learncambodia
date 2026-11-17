@extends('layouts.workspace')
@section('heading', $attempt->snapshot['title'])
@section('content')
    @php
        $label = ($attempt->snapshot['kind'] ?? 'exam') === 'quiz' ? 'Quiz' : 'Exam';
        $back = $enrollment ? route('account.enrollments.show', $enrollment) : $attempt->exam->publicUrl();
    @endphp
    <a class="mb-4 inline-block text-sm font-semibold text-blue-700 dark:text-blue-300" href="{{ $back }}">← Return to {{ $enrollment ? 'course' : strtolower($label) }}</a>
    <a class="mb-4 ml-4 inline-block text-sm font-semibold text-blue-700 dark:text-blue-300" href="{{ route('support.tickets.create') }}">{{ $label }} support</a>
    @if($errors->any())
        <ul role="alert" class="mb-4 list-disc pl-5 text-red-600 dark:text-red-300">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    @endif
    @if($attempt->submitted_at)
        <section class="workspace-panel space-y-3 p-6">
            <h2 class="text-xl font-extrabold">{{ $attempt->passed ? 'Passed' : 'Attempt complete' }}</h2>
            @if($attempt->score !== null)
                <p>{{ $attempt->score }}% scored · {{ $attempt->snapshot['passing_percent'] }}% required</p>
            @elseif(collect($attempt->snapshot['questions'])->contains(fn ($question) => \App\Support\AssessmentQuestions::manual($question)))
                <p>Waiting for writing or speaking grades.</p>
            @else
                <p>Your responses have been recorded.</p>
            @endif
            @if($attempt->snapshot['gamified'] && $attempt->passed)
                <p class="font-semibold text-blue-700 dark:text-blue-300 motion-safe:animate-pulse">Milestone earned: {{ $label }} passed</p>
            @endif
            @foreach($attempt->snapshot['questions'] as $index => $question)
                @php($comment = \App\Support\AssessmentQuestions::manual($question) ? ($attempt->grading[$index]['feedback'] ?? null) : ($question['feedback'] ?? null))
                @if($comment)
                    <p class="break-words whitespace-pre-wrap"><strong>{{ $index + 1 }}.</strong> {{ $comment }}</p>
                @endif
            @endforeach
        </section>
    @elseif(!$attempt->expires_at->isFuture())
        <p class="workspace-panel p-6">This attempt ended when the time limit expired.</p>
    @else
        <section class="workspace-panel mt-2 p-5">
            <h2 class="font-extrabold">Instructions & resources</h2>
            <p class="mt-3 text-sm">Time remaining
                <output data-exam-countdown data-expires="{{ $attempt->expires_at->getTimestamp() }}" class="font-bold tabular-nums"></output>
                <span class="block text-xs text-slate-600 dark:text-slate-300">Submit by {{ $attempt->expires_at->timezone('Asia/Phnom_Penh')->format('H:i:s T') }}.</span>
            </p>
            @if($attempt->snapshot['instructions_html'])
                <x-lesson-content :lesson="(object) ['content_html'=>$attempt->snapshot['instructions_html'], 'content'=>'']" class="mt-4" />
            @endif
        </section>
        <form data-exam-attempt method="post" enctype="multipart/form-data" action="{{ $enrollment ? route('account.exam-attempts.submit', [$enrollment, $attempt]) : route('account.assessment-attempts.submit', $attempt) }}"
            data-feedback-url="{{ $enrollment ? route('account.exam-attempts.feedback', [$enrollment, $attempt]) : route('account.assessment-attempts.feedback', $attempt) }}" class="mt-5 space-y-4">
            @csrf
            @if($attempt->snapshot['gamified'])
                <label class="block text-sm font-semibold"><span data-exam-progress></span>
                    <progress data-exam-meter value="0" max="{{ count($attempt->snapshot['questions']) }}" class="mt-1 block w-full"></progress>
                </label>
            @endif
            @foreach($attempt->snapshot['questions'] as $index => $question)
                @include('workspace.student.assessment-question')
            @endforeach
            <button type="submit" class="rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-bold text-white">Submit attempt</button>
        </form>
    @endif
@endsection
