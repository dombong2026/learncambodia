@props(['attempt'])

<div class="assessment-result">
    <div class="assessment-result-grid">
        <aside class="assessment-result-summary" aria-labelledby="assessment-completed-title">
            <h2 id="assessment-completed-title" class="assessment-result-status">
                <x-workspace-icon name="badge-check" class="h-5 w-5" />
                {{ __('specialized_exam.completed') }}
            </h2>
            {{ $slot }}
        </aside>
        <section class="assessment-result-questions" aria-labelledby="assessment-questionnaire-title">
            <h2 id="assessment-questionnaire-title">{{ __('specialized_exam.questionnaire') }}</h2>
            <ol>
                @foreach($attempt->snapshot['questions'] as $index => $question)
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
                    <li class="assessment-result-question">
                        <span class="assessment-result-number" aria-hidden="true">{{ $loop->iteration }}</span>
                        <div>
                            <p class="assessment-result-prompt">{{ $question['prompt'] }}</p>
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
