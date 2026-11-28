@php
    $format = $question['format'] ?? 'choice';
    $saved = $attempt->answers[$index] ?? null;
    $editable = $saved === null;
    $value = $saved ?? old("answers.$index", '');
    $choices = match($format) {
        'boolean' => ['true' => 'Yes', 'false' => 'No'],
        'likert' => ['1' => 'Strongly disagree', '2' => 'Disagree', '3' => 'Neutral', '4' => 'Agree', '5' => 'Strongly agree'],
        default => array_filter(collect($question)->only('a','b','c','d')->all(), 'filled'),
    };
    $scored = \App\Support\AssessmentQuestions::scored($question);
@endphp
<fieldset data-attempt-question="{{ $index }}" @if(!$editable) data-answer-locked @endif class="workspace-panel space-y-3 p-5">
    <legend class="max-w-full break-words px-2 font-bold">{{ $index + 1 }}. {{ $question['prompt'] }}</legend>
    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $scored ? trans_choice('learning_reader.points', $question['points']) : __('learning_reader.unscored') }}</p>
    @if($format === 'reading')
        <p class="whitespace-pre-wrap break-words rounded-lg border p-3">{{ $question['passage'] }}</p>
    @elseif($format === 'listening')
        <audio controls preload="none" aria-label="Listening audio for question {{ $index + 1 }}" src="{{ ($guestAttempt ?? false) ? route('guest-assessments.audio', [$attempt, $question['media_id']]) : route('editor-media.show', $question['media_id']) }}"></audio>
    @endif

    @if($format === 'writing')
        <label class="builder-field">Your response<textarea data-exam-answer name="answers[{{ $index }}]" maxlength="8000" rows="6">{{ $value }}</textarea></label>
    @elseif($format === 'speaking')
        <label class="builder-field">Upload your recording (up to 10 MB)
            <input type="file" data-exam-answer name="recordings[{{ $index }}]" accept="audio/*,.m4a,.webm" capture>
        </label>
        <div data-recording-controls class="flex flex-wrap items-center gap-3" hidden>
            <button type="button" data-record-start class="rounded-lg border px-3 py-1.5">Record response</button>
            <button type="button" data-record-stop class="rounded-lg border px-3 py-1.5" hidden>Stop recording</button>
            <output data-record-status role="status" class="text-sm"></output>
            <audio data-record-preview controls hidden></audio>
        </div>
    @elseif(in_array($format, ['short', 'fill'], true))
        <label class="builder-field">Your answer<input data-exam-answer name="answers[{{ $index }}]" value="{{ $value }}" maxlength="300" autocomplete="off" @disabled(!$editable)></label>
    @elseif($format === 'order')
        @php
            $keys = $value ? array_values(array_intersect(array_unique(explode(',', $value)), array_keys($choices))) : [];
            if (count($keys) !== count($choices)) {
                $keys = array_keys($choices);
                shuffle($keys);
            }
        @endphp
        <p class="text-sm">Drag items or use the move buttons. Select “Use this order” when you are ready.</p>
        <ol data-order-list class="space-y-2">
            @foreach($keys as $key)
                <li data-order-key="{{ $key }}" draggable="{{ $editable ? 'true' : 'false' }}" class="flex flex-wrap items-center gap-2 rounded-lg border p-3">
                    <span class="min-w-0 flex-1 break-words">{{ $choices[$key] }}</span>
                    <button type="button" data-move="-1" class="rounded border px-2 py-1 text-sm" @disabled(!$editable)>Move up</button>
                    <button type="button" data-move="1" class="rounded border px-2 py-1 text-sm" @disabled(!$editable)>Move down</button>
                </li>
            @endforeach
        </ol>
        <input type="hidden" data-exam-answer value="{{ $value }}" name="answers[{{ $index }}]" @disabled(!$editable)>
        @if($editable)<button type="button" data-use-order class="rounded-lg border px-3 py-1.5 text-sm font-semibold">Use this order</button>@endif
        <output data-order-status role="status" class="text-sm"></output>
    @else
        @foreach($choices as $choice => $text)
            <label class="flex items-start gap-3 rounded-lg border border-slate-300 p-3 text-sm dark:border-slate-700">
                <input data-exam-answer class="mt-0.5" type="radio" name="answers[{{ $index }}]" value="{{ $choice }}" @checked((string)$choice === $value) @disabled(!$editable)>
                <span class="min-w-0 break-words">{{ $text }}</span>
            </label>
        @endforeach
    @endif

    @if($index === 0 && in_array(app()->getLocale(), ['km', 'zh'], true))
        <span hidden data-exam-feedback-labels
            data-correct="{{ __('learning_reader.correct') }}" data-incorrect="{{ __('learning_reader.incorrect') }}"
            data-choose="{{ __('learning_reader.choose') }}" data-checking="{{ __('learning_reader.checking') }}"
            data-error="{{ __('learning_reader.error') }}" data-locked="{{ __('learning_reader.locked') }}"></span>
    @endif
    @if(($attempt->snapshot['live_feedback'] ?? false) && $scored && !\App\Support\AssessmentQuestions::manual($question))
        @if($editable)
            <button type="button" data-exam-check class="rounded-lg border px-3 py-1.5 text-sm font-semibold">{{ __('learning_reader.check') }}</button>
        @endif
        <p data-exam-feedback class="text-sm" role="status">@if(!$editable){{ \App\Support\AssessmentQuestions::correct($question, $saved) ? __('learning_reader.correct') : __('learning_reader.incorrect') }} {{ $question['feedback'] ?? '' }}@endif</p>
    @endif
</fieldset>
