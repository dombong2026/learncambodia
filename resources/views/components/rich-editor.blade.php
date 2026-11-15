@props(['id'=>'rich-content', 'value'=>'', 'html'=>null, 'context'=>'editorial', 'maxLength'=>25000])
<section data-rich-editor data-editor-context="{{ $context }}" data-editor-upload-url="{{ route('editor-media.store') }}"
    class="rich-editor min-w-0 rounded-xl border border-slate-300 dark:border-slate-700" aria-label="Rich text editor">
    <div data-editor-enhanced hidden>
        <div class="rich-editor-toolbar flex flex-wrap items-center gap-0.5 border-b border-slate-200 px-2 py-1.5 dark:border-slate-700" role="toolbar" aria-label="Formatting and media controls">
            <label class="sr-only" for="{{ $id }}-block">Paragraph format</label>
            <select id="{{ $id }}-block" data-editor-block class="rich-tool-select" title="Paragraph format">
                <option value="p">Paragraph</option><option value="h1">Heading 1</option><option value="h2">Heading 2</option><option value="h3">Heading 3</option><option value="h4">Heading 4</option>
                <option value="blockquote">Quote</option><option value="pre">Code block</option>
            </select>
            <span class="rich-tool-divider" aria-hidden="true"></span>
            @foreach(['bold'=>'Bold','italic'=>'Italic','underline'=>'Underline','strikeThrough'=>'Strike','subscript'=>'Subscript','superscript'=>'Superscript'] as $command=>$label)
            <button type="button" data-editor-command="{{ $command }}" class="rich-tool" title="{{ $label }}" aria-label="{{ $label }}">{{ ['bold'=>'B','italic'=>'I','underline'=>'U','strikeThrough'=>'S','subscript'=>'x₂','superscript'=>'x²'][$command] }}</button>
            @endforeach
            <span class="rich-tool-divider" aria-hidden="true"></span>
            @foreach(['insertUnorderedList'=>'Bulleted list','insertOrderedList'=>'Numbered list','outdent'=>'Outdent','indent'=>'Indent','justifyLeft'=>'Align left','justifyCenter'=>'Align center','justifyRight'=>'Align right'] as $command=>$label)
            <button type="button" data-editor-command="{{ $command }}" class="rich-tool" title="{{ $label }}" aria-label="{{ $label }}">{{ ['insertUnorderedList'=>'• ≡','insertOrderedList'=>'1. ≡','outdent'=>'⇤','indent'=>'⇥','justifyLeft'=>'≡','justifyCenter'=>'☰','justifyRight'=>'≡⇥'][$command] }}</button>
            @endforeach
            <span class="rich-tool-divider" aria-hidden="true"></span>
            <button type="button" data-editor-link class="rich-tool" title="Insert or edit a link">Link</button>
            <button type="button" data-editor-command="unlink" class="rich-tool" title="Remove link" aria-label="Remove link">Unlink</button>
            <button type="button" data-editor-table class="rich-tool" title="Insert 2 × 2 table">Table</button>
            <button type="button" data-editor-command="insertHorizontalRule" class="rich-tool" title="Horizontal divider" aria-label="Horizontal divider">—</button>
            <button type="button" data-editor-command="removeFormat" class="rich-tool" title="Clear formatting">Clear</button>
            <button type="button" data-editor-command="undo" class="rich-tool" title="Undo" aria-label="Undo">↶</button>
            <button type="button" data-editor-command="redo" class="rich-tool" title="Redo" aria-label="Redo">↷</button>
            <label class="rich-tool inline-flex cursor-pointer items-center" title="Insert image, audio or video">
                Media
                <input data-editor-upload type="file" accept="image/jpeg,image/png,image/gif,image/webp,audio/mpeg,audio/ogg,audio/wav,video/mp4,video/webm" class="sr-only" aria-label="Upload an image, audio or video">
            </label>
            <span class="rich-tool-divider" aria-hidden="true"></span>
            <div class="ml-auto inline-flex gap-1" role="group" aria-label="Editor mode">
                <button type="button" data-editor-mode="text" aria-pressed="true" class="rich-tool font-bold">Text</button>
                <button type="button" data-editor-mode="html" aria-pressed="false" class="rich-tool font-bold">HTML</button>
            </div>
        </div>
        <div data-editor-visual role="textbox" aria-label="Document content in visual text mode" aria-multiline="true" contenteditable="true"
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
    <label class="sr-only" for="{{ $id }}-plain">Content (plain text fallback)</label>
    <textarea id="{{ $id }}-plain" data-editor-plain name="body" rows="12" maxlength="{{ $maxLength }}" required
        class="w-full rounded-xl border-0 px-4 py-4 text-sm leading-7">{{ $value }}</textarea>
    <textarea data-editor-html name="body_html" hidden aria-label="Saved sanitized HTML">{{ $html }}</textarea>
    @error('body')<p class="px-3 py-2 text-xs text-red-600">{{ $message }}</p>@enderror
    @error('body_html')<p class="px-3 py-2 text-xs text-red-600">{{ $message }}</p>@enderror
</section>
