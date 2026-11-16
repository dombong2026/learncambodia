@props([
    'id' => 'rich-content', 'value' => '', 'html' => null, 'context' => 'editorial', 'maxLength' => 25000,
    'name' => 'body', 'htmlName' => 'body_html', 'labelledBy' => null, 'required' => true,
])
@php
    $courseEditor = in_array($context, ['course', 'lesson'], true);
    $lessonEditor = $context === 'lesson';
@endphp
<section data-rich-editor data-editor-context="{{ $context }}" data-editor-upload-url="{{ route('editor-media.store') }}"
    @class(['rich-editor min-w-0 rounded-xl border border-slate-300 dark:border-slate-700', 'course-description-editor' => $courseEditor])
    aria-label="Rich text editor">
    <div data-editor-enhanced hidden>
        <div class="rich-editor-toolbar flex flex-wrap items-center gap-0.5 border-b border-slate-200 px-2 py-1.5 dark:border-slate-700" role="group" aria-label="Formatting and media controls">
            <div data-editor-formatting class="rich-editor-formatting flex min-w-0 flex-wrap items-center gap-0.5">
                <label class="sr-only" for="{{ $id }}-block">Paragraph format</label>
                <select id="{{ $id }}-block" data-editor-block class="rich-tool-select" title="Paragraph format">
                    <option value="p">Paragraph</option><option value="h1">Heading 1</option><option value="h2">Heading 2</option><option value="h3">Heading 3</option><option value="h4">Heading 4</option>
                    <option value="blockquote">Quote</option><option value="pre">Code block</option>
                </select>
                <label class="sr-only" for="{{ $id }}-size">Text size</label>
                <select id="{{ $id }}-size" data-editor-size class="rich-tool-select" title="Text size">
                    <option value="2">14 px</option><option value="1">12 px</option><option value="3">16 px</option>
                    <option value="4">18 px</option><option value="5">24 px</option><option value="6">32 px</option>
                </select>
                <label class="sr-only" for="{{ $id }}-font">Font family</label>
                <select id="{{ $id }}-font" data-editor-font class="rich-tool-select" title="Font family">
                    <option value="Arial">Arial</option><option value="Georgia">Georgia</option><option value="monospace">Monospace</option>
                </select>
                <span class="rich-tool-divider" aria-hidden="true"></span>
                @foreach(['bold'=>'Bold','italic'=>'Italic','underline'=>'Underline','strikeThrough'=>'Strike','subscript'=>'Subscript','superscript'=>'Superscript'] as $command=>$label)
                <button type="button" data-editor-command="{{ $command }}" class="rich-tool" title="{{ $label }}" aria-label="{{ $label }}">{{ ['bold'=>'B','italic'=>'𝑰','underline'=>'U̲','strikeThrough'=>'S̶','subscript'=>'x₂','superscript'=>'x²'][$command] }}</button>
                @endforeach
                <label class="rich-tool inline-flex cursor-pointer items-center gap-1" title="Text color">Color
                    <input data-editor-color="foreColor" type="color" value="#2563eb" class="h-5 w-5 cursor-pointer border-0 bg-transparent p-0" aria-label="Text color">
                </label>
                <label class="rich-tool inline-flex cursor-pointer items-center gap-1" title="Highlight color">Mark
                    <input data-editor-color="hiliteColor" type="color" value="#f8d478" class="h-5 w-5 cursor-pointer border-0 bg-transparent p-0" aria-label="Highlight color">
                </label>
                <span class="rich-tool-divider" aria-hidden="true"></span>
                @foreach(['insertUnorderedList'=>'Bulleted list','insertOrderedList'=>'Numbered list','outdent'=>'Outdent','indent'=>'Indent','justifyLeft'=>'Align left','justifyCenter'=>'Align center','justifyRight'=>'Align right','justifyFull'=>'Justify'] as $command=>$label)
                <button type="button" data-editor-command="{{ $command }}" class="rich-tool" title="{{ $label }}" aria-label="{{ $label }}">{{ ['insertUnorderedList'=>'• ≡','insertOrderedList'=>'1. ≡','outdent'=>'⇤','indent'=>'⇥','justifyLeft'=>'≡','justifyCenter'=>'☰','justifyRight'=>'≡⇥','justifyFull'=>'☷'][$command] }}</button>
                @endforeach
                <span class="rich-tool-divider" aria-hidden="true"></span>
                <button type="button" data-editor-link class="rich-tool" title="Insert or edit a link">Link</button>
                <button type="button" data-editor-command="unlink" class="rich-tool" title="Remove link" aria-label="Remove link">Unlink</button>
                <button type="button" data-editor-table class="rich-tool" title="Insert a table">Table</button>
                <button type="button" data-editor-command="insertHorizontalRule" class="rich-tool" title="Horizontal divider" aria-label="Horizontal divider">—</button>
                <button type="button" data-editor-command="removeFormat" class="rich-tool" title="Clear formatting">Clear</button>
                <button type="button" data-editor-command="undo" class="rich-tool" title="Undo" aria-label="Undo">↶</button>
                <button type="button" data-editor-command="redo" class="rich-tool" title="Redo" aria-label="Redo">↷</button>
                <label class="rich-tool inline-flex cursor-pointer items-center" title="{{ $lessonEditor ? 'Upload an image, video, audio file or document' : 'Insert image, audio or video' }}">
                    {{ $lessonEditor ? 'Upload' : 'Media' }}
                    <input data-editor-upload type="file" accept="image/jpeg,image/png,image/gif,image/webp,audio/mpeg,audio/ogg,audio/wav,video/mp4,video/webm{{ $lessonEditor ? ',.pdf,.doc,.docx,.xls,.xlsx' : '' }}" class="sr-only" aria-label="{{ $lessonEditor ? 'Upload lesson file' : 'Upload an image, audio or video' }}">
                </label>
                @if($lessonEditor)
                    <button type="button" data-lesson-insert="youtube" class="rich-tool">YouTube</button>
                    <button type="button" data-lesson-insert="code" class="rich-tool">Code</button>
                    <button type="button" data-lesson-insert="button" class="rich-tool">Resource button</button>
                    <button type="button" data-lesson-edit-block class="rich-tool" hidden>Edit element</button>
                    <button type="button" data-lesson-remove-block class="rich-tool" hidden>Remove element</button>
                @endif
                @if($courseEditor)
                    <button type="button" data-editor-media-caption class="rich-tool" hidden>Edit media caption</button>
                    <button type="button" data-editor-media-alt class="rich-tool" hidden>Edit image description</button>
                    <button type="button" data-editor-media-remove class="rich-tool" hidden>Remove media</button>
                    <div data-editor-table-tools class="inline-flex flex-wrap items-center gap-0.5" hidden aria-label="Selected table">
                        @foreach(['add-row'=>'Add row', 'remove-row'=>'Remove row', 'add-column'=>'Add column', 'remove-column'=>'Remove column', 'remove-table'=>'Remove table'] as $action => $label)
                            <button type="button" data-table-action="{{ $action }}" class="rich-tool">{{ $label }}</button>
                        @endforeach
                    </div>
                @endif
            </div>
            <button type="button" data-editor-preview class="rich-tool" title="Preview unsaved content" aria-label="Preview">@if($courseEditor)<x-workspace-icon name="eye" />@else Preview @endif</button>
            <button type="button" data-editor-expand class="rich-tool" aria-pressed="false" title="Expand editor">Expand</button>
            <div class="ml-auto inline-flex gap-1" role="group" aria-label="Editor mode">
                <button type="button" data-editor-mode="text" aria-pressed="true" class="rich-tool font-bold">Text</button>
                <button type="button" data-editor-mode="html" aria-pressed="false" class="rich-tool font-bold">HTML</button>
            </div>
        </div>
        <div data-editor-visual role="textbox" @if($labelledBy) aria-labelledby="{{ $labelledBy }}" @else aria-label="Document content in visual text mode" @endif aria-multiline="true" contenteditable="true"
            class="rich-editor-canvas min-h-64 px-4 py-4 text-sm leading-7 focus:outline-none sm:min-h-80"></div>
        <label class="sr-only" for="{{ $id }}-source">HTML source</label>
        <textarea id="{{ $id }}-source" data-editor-source rows="14" spellcheck="false" hidden
            class="rich-editor-source w-full border-0 px-4 py-4 font-mono text-xs leading-6 focus:outline-none"
            aria-label="Edit safe HTML source"></textarea>
        <div class="flex flex-wrap justify-between gap-2 border-t border-slate-200 px-3 py-2 text-xs text-slate-500 dark:border-slate-700 dark:text-slate-400">
            <span data-editor-notice role="status" aria-live="polite">Select text for formatting, or switch to HTML.</span>
            <span data-editor-count>0 words · 0 characters</span>
        </div>
    </div>
    @if($lessonEditor)
        <dialog data-lesson-insert-dialog aria-labelledby="{{ $id }}-element-heading" class="rich-editor-preview-dialog rounded-xl border border-slate-300 p-5 text-slate-900 shadow-2xl dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
            <h2 id="{{ $id }}-element-heading" data-lesson-insert-title class="mb-4 text-sm font-extrabold">Insert element</h2>
            <div class="space-y-3">
                <label data-lesson-label-field class="builder-field"><span>Label</span><input type="text" maxlength="160"></label>
                <label data-lesson-url-field class="builder-field"><span>URL or video ID</span><input type="text" maxlength="2048"></label>
                <label data-lesson-language-field class="builder-field">Language
                    <select>
                        @foreach(['plaintext'=>'Plain text','html'=>'HTML','css'=>'CSS','javascript'=>'JavaScript','typescript'=>'TypeScript','php'=>'PHP','python'=>'Python','sql'=>'SQL','java'=>'Java','c'=>'C','c++'=>'C++','c#'=>'C#','bash'=>'Bash','json'=>'JSON','ruby'=>'Ruby','go'=>'Go','rust'=>'Rust','swift'=>'Swift','kotlin'=>'Kotlin'] as $language => $label)
                            <option value="{{ $language }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label data-lesson-code-field class="builder-field">Code<textarea rows="7" spellcheck="false" class="font-mono"></textarea></label>
                <p data-lesson-dialog-notice class="text-sm text-red-700 dark:text-red-300" role="alert" hidden></p>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" data-lesson-insert-cancel class="builder-button">Cancel</button>
                <button type="button" data-lesson-insert-apply class="builder-button builder-primary">Insert</button>
            </div>
        </dialog>
    @endif
    <dialog data-editor-preview-dialog aria-label="Live content preview" class="rich-editor-preview-dialog rounded-xl border border-slate-300 p-5 text-slate-900 shadow-2xl dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h2 class="text-sm font-extrabold">Live content preview</h2>
            <button type="button" data-editor-preview-close class="rich-tool" aria-label="Close preview">Close</button>
        </div>
        <div data-editor-preview-content class="rich-public-content {{ $lessonEditor ? 'lesson-rich-content' : '' }} max-h-[65vh] min-h-32 overflow-y-auto text-sm leading-8"></div>
    </dialog>
    @unless($labelledBy)<label class="sr-only" for="{{ $id }}-plain">Content (plain text fallback)</label>@endunless
    <textarea id="{{ $id }}-plain" data-editor-plain name="{{ $name }}" rows="{{ $courseEditor ? 6 : 12 }}" maxlength="{{ $maxLength }}" @required($required)
        class="w-full rounded-xl border-0 px-4 py-4 text-sm leading-7">{{ $value }}</textarea>
    <textarea data-editor-html name="{{ $htmlName }}" @disabled($courseEditor) hidden aria-label="Saved sanitized HTML">{{ $html }}</textarea>
    @error($name)<p class="px-3 py-2 text-xs text-red-600">{{ $message }}</p>@enderror
    @error($htmlName)<p class="px-3 py-2 text-xs text-red-600">{{ $message }}</p>@enderror
</section>
