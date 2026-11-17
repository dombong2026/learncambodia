@props(['lesson', 'enrollment' => null, 'curriculum' => false])

@if(filled($lesson->quiz_questions))
    @php
        $result = $enrollment
            ? \App\Support\LessonQuiz::resultFor(request(), $enrollment->id, $lesson->id)
            : null;
        $answers = $result['answers'] ?? null;
        $questions = $lesson->quiz_questions;
        $correctCount = $answers === null ? null : count(array_filter($questions,
            fn ($question, $index) => $question['correct'] === ($answers[$index] ?? null), ARRAY_FILTER_USE_BOTH));
    @endphp
    <section id="lesson-quiz-{{ $lesson->id }}" aria-labelledby="lesson-quiz-heading-{{ $lesson->id }}"
        class="mt-6 scroll-mt-28 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900 sm:p-5">
        <h3 id="lesson-quiz-heading-{{ $lesson->id }}" class="text-base font-bold">Lesson quiz</h3>
        @if($enrollment)
            @if($answers !== null)
                <p role="status" class="mt-2 text-sm font-semibold">{{ $correctCount }} of {{ count($questions) }} correct.</p>
            @elseif($result !== null)
                <p role="alert" class="mt-2 text-sm font-semibold text-red-700 dark:text-red-300">Choose one answer for each question.</p>
            @endif
            <form method="POST" action="{{ route('account.lessons.quiz', [$enrollment, $lesson]) }}" class="mt-4 space-y-4">
                @csrf
                <input type="hidden" name="view" value="{{ $curriculum ? 'curriculum' : 'lesson' }}">
        @endif
        <div class="mt-4 space-y-4 text-sm">
            @foreach($questions as $index => $question)
                <fieldset class="min-w-0 space-y-2 rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                    <legend class="break-words px-1 font-semibold">{{ $loop->iteration }}. {{ $question['prompt'] }}</legend>
                    @foreach($question['choices'] as $key => $choice)
                        <label class="flex items-start gap-2 rounded-md px-2 py-1.5 {{ $enrollment ? 'cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-800' : '' }}">
                            @if($enrollment)
                                <input class="mt-1" type="radio" name="answers[{{ $index }}]" value="{{ $key }}"
                                    @checked(($answers[$index] ?? null) === $key) required>
                            @endif
                            <span class="min-w-0 break-words">{{ $choice }}
                                @if((!$enrollment || $answers !== null) && $question['correct'] === $key)
                                    <span class="ml-1 font-semibold text-emerald-700 dark:text-emerald-300">Correct answer</span>
                                @endif
                            </span>
                        </label>
                    @endforeach
                    @if((!$enrollment || $answers !== null) && filled($question['feedback']))
                        <p class="break-words px-2 pt-1 text-slate-600 dark:text-slate-300">{{ $question['feedback'] }}</p>
                    @endif
                </fieldset>
            @endforeach
        </div>
        @if($enrollment)
                <button type="submit" class="rounded-lg bg-[#10355e] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#164574]">
                    Check answers
                </button>
            </form>
        @endif
    </section>
@endif
