@extends('layouts.workspace')
@section('heading', $attempt->snapshot['title'])
@section('content')
    <a class="mb-4 inline-block text-sm font-semibold text-blue-700 dark:text-blue-300" href="{{ route('account.enrollments.show',$enrollment) }}">← Return to course</a>
    <a class="mb-4 ml-4 inline-block text-sm font-semibold text-blue-700 dark:text-blue-300" href="{{ route('support.tickets.create') }}">Exam support</a>
    @if($attempt->submitted_at)
        <section class="workspace-panel space-y-3 p-6">
            <h2 class="text-xl font-extrabold">{{ $attempt->passed ? 'Passed' : 'Attempt complete' }}</h2>
            <p>{{ $attempt->score }}% scored · {{ $attempt->snapshot['passing_percent'] }}% required</p>
            @if($attempt->snapshot['gamified'] && $attempt->passed)
                <p class="font-semibold text-blue-700 dark:text-blue-300">Milestone earned: Exam passed</p>
            @endif
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
        <form data-exam-attempt method="post" action="{{ route('account.exam-attempts.submit',[$enrollment,$attempt]) }}" class="mt-5 space-y-4">
            @csrf
            @if($attempt->snapshot['gamified'])
                <p data-exam-progress class="text-sm font-semibold">0 answered of {{ count($attempt->snapshot['questions']) }}</p>
            @endif
            @foreach($attempt->snapshot['questions'] as $index => $question)
                <fieldset class="workspace-panel space-y-3 p-5">
                    <legend class="max-w-full break-words px-2 font-bold">{{ $loop->iteration }}. {{ $question['prompt'] }}</legend>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $question['points'] }} {{ Str::plural('point', $question['points']) }}</p>
                    @if(($question['format'] ?? 'choice') === 'short')
                        <label class="builder-field">Your answer<input data-exam-answer name="answers[{{ $index }}]" maxlength="300" autocomplete="off"></label>
                    @else
                        @php($choices = ($question['format'] ?? 'choice') === 'boolean' ? ['true'=>'True','false'=>'False'] : collect($question)->only('a','b','c','d')->all())
                        @foreach($choices as $choice => $label)
                            @if(filled($label))
                                <label class="flex items-start gap-3 rounded-lg border border-slate-300 p-3 text-sm dark:border-slate-700">
                                    <input data-exam-answer class="mt-0.5" type="radio" name="answers[{{ $index }}]" value="{{ $choice }}">
                                    <span class="min-w-0 break-words">{{ $label }}</span>
                                </label>
                            @endif
                        @endforeach
                    @endif
                </fieldset>
            @endforeach
            <button class="rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-bold text-white">Submit attempt</button>
        </form>
    @endif
@endsection
