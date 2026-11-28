<section class="learning-answer-review" aria-label="{{ __('learning_reader.review') }}">
    <h2>{{ __('learning_reader.review') }}</h2>
    @foreach($attempt->snapshot['questions'] as $index => $question)
        @php
            $format = $question['format'] ?? 'choice';
            $choice = in_array($format, \App\Support\AssessmentQuestions::CHOICES, true);
            $answer = $attempt->answers[$index] ?? null;
            $correct = $choice ? ($question[$question['correct'] ?? ''] ?? null) : null;
            $comment = \App\Support\AssessmentQuestions::manual($question)
                ? ($attempt->grading[$index]['feedback'] ?? null)
                : ($question['feedback'] ?? null);
            $prefix = $correct ? 'ចម្លើយត្រឹមត្រូវ៖ '.$correct.'។ ' : null;
            if ($prefix && is_string($comment) && str_starts_with($comment, $prefix)) {
                $comment = substr($comment, strlen($prefix));
            }
        @endphp
        @if($choice || $comment)
            <article>
                <h3>{{ $index + 1 }}. {{ $question['prompt'] }}</h3>
                @if($choice)
                    <p><strong>{{ __('learning_reader.your_answer') }}:</strong> {{ $question[$answer ?? ''] ?? __('learning_reader.unanswered') }}
                        @if($answer !== null) · {{ __(\App\Support\AssessmentQuestions::correct($question, $answer) ? 'learning_reader.correct' : 'learning_reader.incorrect') }}@endif
                    </p>
                    @if($correct)<p><strong>{{ __('learning_reader.correct_answer') }}:</strong> {{ $correct }}</p>@endif
                @endif
                @if($comment)<p class="whitespace-pre-wrap"><strong>{{ __('learning_reader.explanation') }}:</strong> {{ $comment }}</p>@endif
            </article>
        @endif
    @endforeach
</section>
