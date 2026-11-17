<fieldset class="builder-editor space-y-3" data-quiz-question>
    <legend class="px-1 text-sm font-bold" data-quiz-question-label>Question {{ $index + 1 }}</legend>
    <label class="builder-field">Question
        <textarea name="quiz_questions[{{ $index }}][prompt]" rows="2" maxlength="500" required>{{ is_string($question['prompt'] ?? null) ? $question['prompt'] : '' }}</textarea>
    </label>
    <div class="grid gap-3 sm:grid-cols-2">
        @foreach(\App\Support\LessonQuiz::CHOICES as $choice)
            @php($answer = data_get($question, "choices.{$choice}"))
            <label class="builder-field">Choice {{ strtoupper($choice) }}{{ in_array($choice, ['c', 'd'], true) ? ' (optional)' : '' }}
                <input name="quiz_questions[{{ $index }}][choices][{{ $choice }}]" maxlength="300" value="{{ is_string($answer) ? $answer : '' }}" @required(in_array($choice, ['a', 'b'], true))>
            </label>
        @endforeach
    </div>
    <div class="grid gap-3 sm:grid-cols-[10rem_1fr]">
        <label class="builder-field">Correct choice
            <select class="rounded-xl border border-[var(--ws-border)] bg-[var(--ws-input)] px-3 py-2" name="quiz_questions[{{ $index }}][correct]" required>
                <option value="">Choose an answer</option>
                @foreach(\App\Support\LessonQuiz::CHOICES as $choice)
                    <option value="{{ $choice }}" @selected(($question['correct'] ?? null) === $choice)>{{ strtoupper($choice) }}</option>
                @endforeach
            </select>
        </label>
        <label class="builder-field">Feedback (optional)
            <textarea name="quiz_questions[{{ $index }}][feedback]" rows="2" maxlength="700">{{ is_string($question['feedback'] ?? null) ? $question['feedback'] : '' }}</textarea>
        </label>
    </div>
    <button type="button" class="builder-button" data-quiz-remove>Remove question</button>
</fieldset>
