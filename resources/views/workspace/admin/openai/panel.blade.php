@php
    $openAi = \App\Support\ManagedOpenAiSettings::saved();
    $openAiContexts = $openAiContext === 'exam' ? ['exam', 'test'] : [$openAiContext];
    $openAiTextContexts = array_filter($openAiContexts, fn ($context) => \App\Support\ManagedOpenAiSettings::enabled($openAi, $context));
    $openAiAudio = in_array($openAiContext, ['exam', 'quiz', 'test'], true)
        && \App\Support\ManagedOpenAiSettings::enabled($openAi, 'audio');
    $openAiContexts = $openAiAudio ? $openAiContexts : $openAiTextContexts;
    $audioOnly = $openAiAudio && $openAiTextContexts === [];
@endphp
@if($openAiContexts !== [])
    @once
        @push('workspace_styles')
            <script defer src="{{ asset('js/openai-authoring.js') }}?v=2"></script>
        @endpush
    @endonce
    <details class="workspace-panel space-y-3 p-4 sm:p-5 {{ $openAiClass ?? '' }}" data-openai-authoring
        data-endpoint="{{ route('admin.course-builder.openai.draft') }}">
        <summary class="cursor-pointer font-extrabold">{{ $audioOnly ? 'Record listening audio' : 'Draft with OpenAI' }}</summary>
        <div class="space-y-3">
            @php($labels = \App\Support\ManagedOpenAiSettings::FEATURES)
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="builder-field" @if(count($openAiContexts) === 1 || $audioOnly) hidden @endif>Editor
                    <select data-ai-context>
                        @foreach($openAiContexts as $context)
                            <option value="{{ $context }}" data-text="{{ in_array($context, $openAiTextContexts, true) ? 1 : 0 }}" @selected($context === (($placementDraft ?? false) && in_array('test', $openAiContexts, true) ? 'test' : $openAiContext))>{{ $labels[$context] }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="builder-field" @if($audioOnly) hidden @endif>Task
                    <select data-ai-task>
                        @unless($audioOnly)
                            @foreach($openAiTasks as $task => $label)<option value="{{ $task }}">{{ $label }}</option>@endforeach
                        @endunless
                        @if($openAiAudio)<option value="audio">Record listening audio</option>@endif
                    </select>
                </label>
                <label class="builder-field" data-ai-level-field>Level
                    <select data-ai-level>
                        @foreach(['Mixed','A1','A2','B1','B2','C1','C2'] as $level)
                            <option @selected(($openAiLevel ?? '') === $level)>{{ $level }}</option>
                        @endforeach
                    </select>
                </label>
                @if(array_key_exists('questions', $openAiTasks))
                    <label class="builder-field" data-ai-count-field>Questions
                        <input data-ai-count type="number" min="1" max="10" value="5">
                    </label>
                @endif
            </div>
            <label class="builder-field"><span data-ai-topic-label>Topic and focus</span>
                <textarea data-ai-topic rows="3" maxlength="1800"></textarea>
            </label>
            <button type="button" data-ai-generate class="rounded-xl bg-blue-700 px-4 py-2 text-sm font-bold text-white">Generate draft</button>
            <p data-ai-status role="status" class="text-sm text-slate-700 dark:text-slate-300"></p>
            <div data-ai-preview hidden class="space-y-3">
                <pre data-ai-result class="max-h-72 overflow-auto whitespace-pre-wrap break-words rounded-xl border border-slate-300 p-3 font-sans text-sm dark:border-slate-700"></pre>
                @if($openAiAudio)
                    <div data-ai-audio-tools hidden class="space-y-3">
                        <p class="text-sm">Listen and check that the recording matches your passage.</p>
                        <audio data-ai-player controls class="w-full"></audio>
                        <label class="builder-field">Listening question
                            <select data-ai-listening-row></select>
                        </label>
                        <a data-ai-download download="listening-audio.wav" class="inline-flex text-sm font-semibold underline">Download WAV</a>
                    </div>
                @endif
                <button type="button" data-ai-apply class="rounded-xl border border-slate-400 px-4 py-2 text-sm font-semibold">Use draft</button>
            </div>
        </div>
    </details>
@endif
