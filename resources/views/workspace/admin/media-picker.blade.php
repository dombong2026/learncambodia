<dialog data-media-picker data-index-url="{{ route('admin.media-library.index', [], false) }}" data-upload-url="{{ route('admin.media-library.store', [], false) }}" aria-labelledby="media-picker-heading" class="fixed inset-0 m-auto max-h-[85vh] w-[calc(100%-2rem)] max-w-xl overflow-auto rounded-2xl border border-slate-200 bg-white p-5 text-slate-900 shadow-2xl backdrop:bg-slate-950/70 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 id="media-picker-heading" class="text-lg font-extrabold">Media Library</h2>
        <button type="button" data-close-media-library class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold dark:border-slate-600">Close</button>
    </div>
    <form data-media-search class="mt-4 flex flex-wrap gap-2">
        <label for="media-picker-search" class="sr-only">Search Media Library</label>
        <input id="media-picker-search" name="q" type="search" maxlength="80" placeholder="Search filenames" class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-transparent px-3 py-2 text-sm dark:border-slate-700">
        <button type="submit" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold dark:border-slate-600">Search</button>
    </form>
    <div data-media-results class="mt-4 grid gap-2 sm:grid-cols-2"></div>
    <nav aria-label="Media results" class="mt-3 flex gap-2">
        <button type="button" data-media-prev disabled class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-bold disabled:opacity-50 dark:border-slate-600">Previous</button>
        <button type="button" data-media-next disabled class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-bold disabled:opacity-50 dark:border-slate-600">Next</button>
    </nav>
    <label class="mt-4 block text-xs font-semibold">Upload a file to the library
        <input type="file" data-media-upload class="mt-2 block w-full text-xs">
    </label>
    <p data-media-status role="status" class="mt-3 text-xs text-slate-600 dark:text-slate-300"></p>
</dialog>
