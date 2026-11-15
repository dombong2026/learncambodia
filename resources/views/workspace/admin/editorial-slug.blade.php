<div>
    <label for="slug" class="mb-1 block text-xs font-semibold">URL slug</label>
    <div class="relative">
        <input id="slug" name="slug" data-editor-slug value="{{ old('slug', $entry->slug) }}" maxlength="200" placeholder="Generated from title if empty" class="w-full rounded-lg border border-slate-300 bg-transparent py-2 pr-11 pl-3 text-xs dark:border-slate-700">
        <button type="button" data-copy-slug data-slug-url="{{ route('admin.editorial.slug', ['kind' => $kind], false) }}" aria-label="Click to copy" title="Click to copy" class="absolute inset-y-0 right-0 flex w-10 items-center justify-center rounded-r-lg text-slate-500 hover:bg-slate-100 hover:text-blue-700 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-blue-300">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" class="h-4 w-4" aria-hidden="true"><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/></svg>
        </button>
    </div>
    <p data-copy-slug-status class="sr-only" role="status"></p>
    @error('slug')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>
