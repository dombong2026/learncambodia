@extends('layouts.workspace')
@php
    $quiz = $exam->kind === 'quiz';
    $manager = $quiz ? 'admin.quizzes-manager' : 'admin.exams';
    $assessmentLabel = $quiz ? 'quiz' : 'exam';
@endphp
@section('heading', $quiz ? 'Quiz Builder' : 'Exam Builder')
@section('heading_action')
    <a class="text-sm font-semibold underline" href="{{ route($manager.'.index') }}">{{ $quiz ? 'Quizzes' : 'Exams' }}</a>
@endsection
@section('content')
    @if($errors->any())
        <ul role="alert" class="mb-4 list-disc space-y-1 pl-5 text-red-600 dark:text-red-300">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    @endif
    <form data-exam-builder data-question-limit="{{ $quiz ? 50 : 200 }}" data-media-upload="{{ route('editor-media.store') }}" method="post" action="{{ $exam->exists ? route($manager.'.update', $exam) : route($manager.'.store') }}" class="space-y-5">
        @csrf @if($exam->exists)@method('PATCH')@endif
        <fieldset class="workspace-panel grid gap-4 p-5 sm:grid-cols-2">
            <label class="builder-field sm:col-span-2">{{ ucfirst($assessmentLabel) }} title<input name="title" required maxlength="160" value="{{ old('title', $exam->title) }}"></label>
            @unless($quiz)
            <label class="builder-field">Course (optional)
                <select name="course_id" class="rounded-xl border px-3 py-2">
                    <option value="">Standalone (no course)</option>
                    @foreach($courses as $course)
                        <option value="{{ $course->id }}" @selected((int) old('course_id', $exam->course_id) === $course->id)>{{ $course->title }}</option>
                    @endforeach
                </select>
            </label>
            @endunless
            <label class="builder-field">{{ ucfirst($assessmentLabel) }} type
                <select name="exam_type_id" required class="rounded-xl border px-3 py-2">
                    <option value="">Choose a type</option>
                    @foreach($types as $type)
                        <option value="{{ $type->id }}" @selected((int) old('exam_type_id', $exam->exam_type_id) === $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
                <a class="text-blue-600 dark:text-blue-300" href="{{ route($quiz ? 'admin.quiz-types.index' : 'admin.exam-types.index') }}">Manage types</a>
            </label>
            <div class="grid gap-4 sm:col-span-2 sm:grid-cols-3">
                @foreach(['time_limit'=>['Time limit (minutes)',$quiz ? 5 : 60,$quiz ? 30 : 240], 'passing_percent'=>['Pass score (%)',70,100], 'attempt_limit'=>['Attempts allowed',2,10]] as $field=>[$label,$default,$maximum])
                    <label class="builder-field">{{ $label }}
                        <input name="{{ $field }}" type="number" min="1" max="{{ $maximum }}" value="{{ old($field, $exam->$field ?? $default) }}" required>
                    </label>
                @endforeach
            </div>
            <x-learning-detail-fields :record="$exam" :kind="$assessmentLabel" class="sm:col-span-2 sm:grid-cols-2" />
            @foreach(['shuffle'=>'Shuffle questions', 'gamified'=>'Show progress and milestones', 'live_feedback'=>'Let learners check answers during the attempt', 'published'=>'Publish for learners'] as $key => $label)
                <label class="flex items-center gap-2 text-sm font-semibold">
                    <input type="hidden" name="{{ $key }}" value="0">
                    <input type="checkbox" name="{{ $key }}" value="1" @checked(old($key,$exam->$key ?? ($quiz && in_array($key, ['gamified', 'live_feedback'], true))))>{{ $label }}
                </label>
            @endforeach
            <p class="text-xs text-slate-600 dark:text-slate-300 sm:col-span-2">Checking an answer locks that response. Writing and speaking are graded after submission.</p>
            @unless($quiz)
                <div data-standalone-pricing @if(old('course_id', $exam->course_id)) hidden @endif class="sm:col-span-2">
                    @include('workspace.admin.course-builder.pricing', ['course' => $exam])
                </div>
            @endunless
        </fieldset>

        @if($quiz)
            @include('workspace.admin.editorial-featured-media', ['entry' => $exam])
        @endif

        <section class="workspace-panel space-y-3 p-5">
            <h2 id="exam-instructions-label" class="font-extrabold">Instructions, activities & resources</h2>
            <p class="text-sm text-slate-600 dark:text-slate-300">Add task instructions and reading passages. Upload video, audio, images, PDF, Word, or Excel files.</p>
            <x-rich-editor id="exam-instructions" context="exam" name="instructions" html-name="instructions_html"
                labelled-by="exam-instructions-label" :required="false" :max-length="15000"
                :value="old('instructions')" :html="old('instructions_html', $exam->instructions_html)" />
        </section>

        <section class="workspace-panel space-y-4 p-5">
            <h2 class="font-extrabold">Questions</h2>
            <div data-exam-questions class="space-y-4">
                @foreach(old('questions', $exam->questions ?? [[]]) as $index => $question)
                    @include('workspace.admin.examination.question', ['index' => $index, 'question' => $question])
                @endforeach
            </div>
            <template data-exam-template>@include('workspace.admin.examination.question', ['index' => 0, 'question' => []])</template>
            <button type="button" data-exam-add class="rounded-xl border border-slate-400 px-4 py-2 text-sm font-semibold">Add question</button>
        </section>
        <button type="submit" class="rounded-xl bg-blue-700 px-5 py-3 text-sm font-bold text-white">Save {{ $assessmentLabel }}</button>
    </form>
    @if($quiz)@include('workspace.admin.media-picker')@endif
    @if($exam->published && !$exam->course_id)
        <a class="my-5 inline-block text-sm font-semibold underline" href="{{ $exam->publicUrl() }}">Open learner page</a>
    @endif
    @if($accessRequests->isNotEmpty())
        <section class="workspace-panel mt-5 space-y-4 p-5">
            <h2 class="font-extrabold">Access requests</h2>
            @foreach($accessRequests as $access)
                <article class="rounded-xl border p-4">
                    <p>{{ $access->student->name }} · {{ ucfirst($access->status) }} · {{ $access->quote['model'] === 'paid' ? \App\Support\CoursePricing::formatAmount($access->quote['amount_minor'], $access->quote['currency']) : $access->quote['plan'] }}</p>
                    @if($access->status === 'pending')
                        <form method="post" action="{{ route('admin.assessment-access.review', $access) }}" class="mt-3 space-y-3">
                            @csrf @method('PATCH')
                            <label class="builder-field">Payment or membership verification reference<input name="reference" maxlength="160"></label>
                            <button class="rounded-xl border px-4 py-2 font-semibold" name="decision" value="approved">Approve access</button>
                            <button class="rounded-xl border px-4 py-2 font-semibold" name="decision" value="declined">Decline access</button>
                        </form>
                    @elseif($access->reference)
                        <p class="mt-2 text-sm">{{ $access->reference }}</p>
                    @endif
                </article>
            @endforeach
            {{ $accessRequests->links() }}
        </section>
    @endif
    @if($attempts)
        <section class="workspace-panel mt-5 space-y-3 p-5">
            <h2 class="font-extrabold">Submitted attempts</h2>
            @forelse($attempts as $result)
                <article class="space-y-3 rounded-xl border p-4">
                    <p class="text-sm">{{ ($result->student ?? $result->enrollment?->student)?->name }} · {{ $result->score !== null ? $result->score.'%' : 'Submitted' }} · {{ $result->submitted_at->toDateString() }}</p>
                    @foreach($result->snapshot['questions'] as $number => $question)
                        @continue(!\App\Support\AssessmentQuestions::manual($question))
                        <details class="space-y-2">
                            <summary class="cursor-pointer font-semibold">{{ $number + 1 }}. {{ $question['prompt'] }} · {{ isset($result->grading[$number]) ? 'Graded' : 'Awaiting grade' }}</summary>
                            @if(filled($result->answers[$number] ?? null))
                                @if($question['format'] === 'speaking')
                                    <audio controls preload="none" src="{{ route('assessments.recording', [$result, $number]) }}"></audio>
                                @else
                                    <p class="whitespace-pre-wrap break-words">{{ $result->answers[$number] }}</p>
                                @endif
                            @else
                                <p>No response submitted.</p>
                            @endif
                            <form method="post" action="{{ route('admin.assessment-attempts.grade', $result) }}" class="space-y-2">
                                @csrf @method('PATCH')
                                <label class="builder-field">Points (out of {{ $question['points'] }})
                                    <input name="grades[{{ $number }}][points]" type="number" min="0" max="{{ $question['points'] }}" required value="{{ $result->grading[$number]['points'] ?? '' }}">
                                </label>
                                <label class="builder-field">Feedback
                                    <textarea name="grades[{{ $number }}][feedback]" maxlength="1000">{{ $result->grading[$number]['feedback'] ?? '' }}</textarea>
                                </label>
                                <button class="rounded-xl border px-4 py-2 text-sm font-semibold">Save grade</button>
                            </form>
                        </details>
                    @endforeach
                </article>
            @empty
                <p class="text-sm">No submitted attempts.</p>
            @endforelse
            {{ $attempts->links() }}
        </section>
    @endif
@endsection
