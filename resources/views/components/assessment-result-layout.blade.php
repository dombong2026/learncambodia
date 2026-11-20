@props(['attempt'])

@php
    $statuses = [];
    $counts = array_fill_keys(['correct', 'incorrect', 'unanswered', 'pending', 'partial', 'unscored'], 0);
    $mistakeStatuses = ['incorrect', 'unanswered', 'partial'];
    foreach ($attempt->snapshot['questions'] as $index => $question) {
        $status = \App\Support\AssessmentQuestions::reviewStatus(
            $question, (string) ($attempt->answers[$index] ?? ''), $attempt->grading[$index] ?? null,
        );
        $statuses[$index] = $status;
        $counts[$status]++;
    }
    $reviewingMistakes = request()->query('review') === 'mistakes';
    $next = \App\Support\AssessmentCatalog::nextPractice($attempt, request()->user());
@endphp

<div class="assessment-result">
    <div class="assessment-result-grid">
        <aside class="assessment-result-summary" aria-labelledby="assessment-completed-title">
            <h2 id="assessment-completed-title" class="assessment-result-status">
                <x-workspace-icon name="badge-check" class="h-5 w-5" />
                {{ __('specialized_exam.completed') }}
            </h2>
            <dl class="assessment-review-stats">
                @foreach($counts as $status => $count)
                    @continue($count === 0 && in_array($status, ['partial', 'unscored'], true))
                    <div data-review-count="{{ $status }}">
                        <dt>{{ __('specialized_exam.review_'.$status) }}</dt>
                        <dd>{{ $count }}</dd>
                    </div>
                @endforeach
            </dl>
            {{ $slot }}
            <section class="assessment-result-next" aria-labelledby="assessment-next-title">
                <h3 id="assessment-next-title">{{ __('specialized_exam.next_activity') }}</h3>
                <a href="{{ $next?->publicUrl() ?? route('assessments.quizzes') }}">
                    <x-workspace-icon name="study-quiz" class="h-5 w-5" />
                    <span>{{ $next?->title ?? __('specialized_exam.browse_quizzes') }}</span>
                </a>
            </section>
        </aside>
        <section class="assessment-result-questions" aria-labelledby="assessment-questionnaire-title">
            <h2 id="assessment-questionnaire-title">{{ __('specialized_exam.questionnaire') }}</h2>
            <div class="assessment-review-tabs">
                <a href="{{ request()->fullUrlWithoutQuery('review') }}#assessment-questionnaire-title" @if(!$reviewingMistakes) aria-current="true" @endif>{{ __('specialized_exam.all_questions') }}</a>
                <a href="{{ request()->fullUrlWithQuery(['review' => 'mistakes']) }}#assessment-questionnaire-title" @if($reviewingMistakes) aria-current="true" @endif>{{ __('specialized_exam.review_mistakes') }}</a>
            </div>
            @if($reviewingMistakes && !$counts['incorrect'] && !$counts['unanswered'] && !$counts['partial'])
                <p>{{ __('specialized_exam.no_mistakes') }}</p>
            @endif
            <ol>
                @foreach($attempt->snapshot['questions'] as $index => $question)
                    @continue($reviewingMistakes && !in_array($statuses[$index], $mistakeStatuses, true))
                    @php
                        $answer = (string) ($attempt->answers[$index] ?? '');
                        $format = $question['format'] ?? 'choice';
                        $options = array_intersect_key($question, ['a' => true, 'b' => true, 'c' => true, 'd' => true]);
                        $response = blank($answer) ? __('specialized_exam.unanswered') : match($format) {
                            'speaking' => __('specialized_exam.recording_submitted'),
                            'boolean' => match($answer) {
                                'true' => __('specialized_exam.answer_yes'),
                                'false' => __('specialized_exam.answer_no'),
                                default => $answer,
                            },
                            'order' => implode(' → ', array_map(fn ($key) => $options[$key] ?? $key, explode(',', $answer))),
                            'choice', 'reading', 'listening' => $options[$answer] ?? $answer,
                            default => $answer,
                        };
                        $feedback = \App\Support\AssessmentQuestions::manual($question)
                            ? ($attempt->grading[$index]['feedback'] ?? null)
                            : ($question['feedback'] ?? null);
                    @endphp
                    <li class="assessment-result-question" data-review-status="{{ $statuses[$index] }}">
                        <span class="assessment-result-number" aria-hidden="true">{{ $loop->iteration }}</span>
                        <div>
                            <p class="assessment-result-prompt">{{ $question['prompt'] }}</p>
                            <p class="assessment-review-outcome">{{ __('specialized_exam.review_'.$statuses[$index]) }}</p>
                            <p class="assessment-result-response"><strong>{{ __('specialized_exam.your_answer') }}</strong> {{ $response }}</p>
                            @if(filled($feedback))
                                <p class="assessment-result-response"><strong>{{ __('specialized_exam.feedback') }}</strong> {{ $feedback }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>
    </div>
</div>
