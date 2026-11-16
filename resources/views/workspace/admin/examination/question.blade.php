<fieldset data-exam-question class="min-w-0 rounded-xl border border-slate-300 p-4 dark:border-slate-700">
    <legend data-exam-number class="px-1 font-bold">Question {{ $index + 1 }}</legend>
    <label class="builder-field">Prompt<textarea name="questions[{{ $index }}][prompt]" rows="2" maxlength="600" required>{{ $question['prompt'] ?? '' }}</textarea></label>
    <div class="mt-4 grid gap-3 sm:grid-cols-2">
        <label class="builder-field sm:col-span-2">Activity type
            <select data-exam-format name="questions[{{ $index }}][format]" class="rounded-xl border px-3 py-2">
                @foreach(\App\Support\AssessmentQuestions::FORMATS as $value => $label)
                    <option value="{{ $value }}" @selected(($question['format'] ?? 'choice') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        @foreach(['a','b','c','d'] as $choice)
            <label class="builder-field" data-exam-option>{{ strtoupper($choice) }}{{ in_array($choice,['c','d']) ? ' (optional)' : '' }}
                <input name="questions[{{ $index }}][{{ $choice }}]" maxlength="300" value="{{ $question[$choice] ?? '' }}">
            </label>
        @endforeach
        <label class="builder-field" data-exam-key>Correct answer
            <input data-exam-correct name="questions[{{ $index }}][correct]" maxlength="300" value="{{ $question['correct'] ?? '' }}">
            <small data-exam-answer-tip class="text-slate-600 dark:text-slate-300"></small>
        </label>
        <label data-exam-reading class="builder-field sm:col-span-2">Reading passage
            <textarea name="questions[{{ $index }}][passage]" rows="5" maxlength="12000">{{ $question['passage'] ?? '' }}</textarea>
        </label>
        <div data-exam-listening class="space-y-2 sm:col-span-2">
            <label class="builder-field">Listening audio<input type="file" data-exam-audio accept="audio/*"></label>
            <input type="hidden" data-exam-media-id name="questions[{{ $index }}][media_id]" value="{{ $question['media_id'] ?? '' }}">
            <audio controls preload="none" @if(isset($question['media_id'])) src="{{ route('editor-media.show', $question['media_id']) }}" @else hidden @endif></audio>
            <p data-exam-upload-status class="text-sm" role="status"></p>
        </div>
        <label class="builder-field sm:col-span-2">Answer feedback (optional)
            <textarea name="questions[{{ $index }}][feedback]" rows="2" maxlength="800">{{ $question['feedback'] ?? '' }}</textarea>
        </label>
        <label class="builder-field">Points<input name="questions[{{ $index }}][points]" type="number" min="1" max="100" required value="{{ $question['points'] ?? 1 }}"></label>
    </div>
    <div class="mt-4 flex flex-wrap gap-2 text-sm font-semibold">
        <button type="button" data-exam-up class="rounded-lg border px-3 py-1.5">Move up</button>
        <button type="button" data-exam-down class="rounded-lg border px-3 py-1.5">Move down</button>
        <button type="button" data-exam-remove class="rounded-lg border px-3 py-1.5">Remove</button>
    </div>
</fieldset>
