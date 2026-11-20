@props(['lesson', 'enrollment' => null, 'attempt' => null, 'retry' => null, 'history' => null])

@if(filled($lesson->quiz_questions))
    @php
        $questions = $retry ? $retry->missedQuestions() : ($attempt?->questions ?? $lesson->quiz_questions);
        $answers = $attempt?->answers ?? [];
        $isAnswering = $enrollment && !$attempt;
        $showFeedback = !$enrollment || $attempt;
        $quizUrl = fn (array $parameters = []) => route('account.lessons.watch',
            [$enrollment, $lesson, 'viewer' => 'quiz'] + $parameters + request()->only('lang')).'#lesson-quiz-'.$lesson->id;
    @endphp
    <section id="lesson-quiz-{{ $lesson->id }}" aria-labelledby="lesson-quiz-heading-{{ $lesson->id }}"
        class="mt-6 scroll-mt-28 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900 sm:p-5">
        <h3 id="lesson-quiz-heading-{{ $lesson->id }}" class="text-base font-bold">{{ __('focused_learning.lesson_quiz') }}</h3>
        @if($attempt)
            <p role="status" class="mt-2 text-sm font-semibold">{{ __('focused_learning.quiz_score', ['score' => $attempt->correct_count, 'total' => count($questions)]) }}</p>
        @endif
        @if($retry)
            <p class="mt-2 text-sm">{{ __('focused_learning.retry_missed') }}</p>
        @endif
        @error('answers')
            <p role="alert" class="mt-2 text-sm font-semibold text-red-700 dark:text-red-300">{{ $message }}</p>
        @enderror
        @if($isAnswering)
            <form method="POST" action="{{ route('account.lessons.quiz', [$enrollment, $lesson] + request()->only('lang')) }}" class="mt-4 space-y-4">
                @csrf
                @if($retry)
                    <input type="hidden" name="retry" value="{{ $retry->id }}">
                @endif
        @endif
        <div class="mt-4 space-y-4 text-sm">
            @foreach($questions as $index => $question)
                <fieldset class="min-w-0 space-y-2 rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                    <legend class="break-words px-1 font-semibold">{{ $loop->iteration }}. {{ $question['prompt'] }}</legend>
                    @foreach($question['choices'] as $key => $choice)
                        @if(filled($choice))
                            <label class="flex items-start gap-2 rounded-md px-2 py-1.5 {{ $isAnswering ? 'cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-800' : '' }}">
                                @if($isAnswering)
                                    <input class="mt-1" type="radio" name="answers[{{ $index }}]" value="{{ $key }}" required>
                                @endif
                                <span class="min-w-0 break-words">{{ $choice }}
                                    @if(($answers[$index] ?? null) === $key)
                                        <span class="ml-1 font-semibold">{{ __('focused_learning.your_answer') }}</span>
                                    @endif
                                    @if($showFeedback && $question['correct'] === $key)
                                        <span class="ml-1 font-semibold text-emerald-700 dark:text-emerald-300">{{ __('focused_learning.correct_answer') }}</span>
                                    @endif
                                </span>
                            </label>
                        @endif
                    @endforeach
                    @if($showFeedback && filled($question['feedback'] ?? null))
                        <p class="break-words px-2 pt-1 text-slate-600 dark:text-slate-300">{{ $question['feedback'] }}</p>
                    @endif
                </fieldset>
            @endforeach
        </div>
        @if($isAnswering)
                <button type="submit" class="fl-button fl-primary">{{ __('focused_learning.check_answers') }}</button>
            </form>
        @endif
        @if($attempt)
            <div class="mt-4 flex flex-wrap gap-2">
                @if($attempt->missedQuestions() !== [])
                    <a class="fl-button fl-primary" href="{{ $quizUrl(['retry' => $attempt->id]) }}">{{ __('focused_learning.retry_missed') }}</a>
                @endif
                <a class="fl-button" href="{{ $quizUrl(['start' => 1]) }}">{{ __('focused_learning.retry_full') }}</a>
            </div>
        @endif
        @if($history && ($history->isNotEmpty() || $history->previousPageUrl()))
            <details class="mt-5 border-t border-slate-200 pt-4 dark:border-slate-700" @if($history->currentPage() > 1) open @endif>
                <summary class="cursor-pointer text-sm font-bold">{{ __('focused_learning.quiz_history') }}</summary>
                <ul class="mt-2 space-y-2 text-sm">
                    @foreach($history as $entry)
                        <li>
                            <a class="fl-link break-words" href="{{ $quizUrl(['attempt' => $entry->id]) }}" @if($attempt?->id === $entry->id) aria-current="true" @endif>
                                {{ $entry->created_at->timezone('Asia/Phnom_Penh')->format('d M Y, H:i') }} ·
                                {{ __('focused_learning.quiz_score', ['score' => $entry->correct_count, 'total' => count($entry->questions)]) }}
                                @if($entry->retry_of_id) · {{ __('focused_learning.missed_questions_retry') }}@endif
                            </a>
                        </li>
                    @endforeach
                </ul>
                {{ $history->links() }}
            </details>
        @endif
    </section>
@endif
