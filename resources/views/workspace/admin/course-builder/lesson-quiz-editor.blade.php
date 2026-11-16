@php
    $quizLimit = \App\Support\LessonQuiz::MAX_QUESTIONS;
    $quizRows = collect((array) (old('lesson_quiz_present')
        ? old('quiz_questions', []) : $editingLesson?->quiz_questions))
        ->filter(fn ($row) => is_array($row))->take($quizLimit)->values();
@endphp
<details class="builder-editor space-y-3" data-lesson-quiz-editor @if($errors->has('quiz_questions') || $errors->has('quiz_questions.*')) open @endif>
    <summary class="cursor-pointer text-sm font-bold">Lesson quiz <span class="ml-2 font-normal text-slate-600 dark:text-slate-300">Optional</span></summary>
    <p class="text-xs text-slate-600 dark:text-slate-300">Add up to {{ $quizLimit }} multiple-choice questions to show after this lesson.</p>
    <input type="hidden" name="lesson_quiz_present" value="1">
    <div data-quiz-questions class="space-y-3">
        @foreach($quizRows as $index => $question)
            @include('workspace.admin.course-builder.lesson-quiz-question')
        @endforeach
    </div>
    @if($errors->has('quiz_questions') || $errors->has('quiz_questions.*'))
        <ul class="space-y-1 text-sm text-red-700 dark:text-red-300">
            @foreach(array_unique(array_merge($errors->get('quiz_questions'), ...array_values($errors->get('quiz_questions.*')))) as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    @endif
    <button type="button" class="builder-button" data-quiz-add data-limit="{{ $quizLimit }}" hidden>+ Add question</button>
    <template data-quiz-template>
        @include('workspace.admin.course-builder.lesson-quiz-question', ['index' => 0, 'question' => []])
    </template>
</details>
