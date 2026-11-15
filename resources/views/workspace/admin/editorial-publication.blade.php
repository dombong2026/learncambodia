<section aria-labelledby="publication-heading" class="space-y-3 rounded-2xl border border-slate-200 p-4 dark:border-slate-700">
    <div class="flex items-center justify-between gap-3">
        <h2 id="publication-heading" class="text-sm font-extrabold">Publication</h2>
        <span class="text-xs font-bold {{ $published ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-700 dark:text-amber-300' }}">{{ ucfirst(str_replace('_', ' ', $entry->status ?: 'draft')) }}</span>
    </div>
    @if($entry->published_at && $published)
        <p class="text-xs text-slate-500 dark:text-slate-400">Published <time datetime="{{ $entry->published_at->toAtomString() }}">{{ $entry->published_at->toFormattedDateString() }}</time></p>
    @endif
    @if($canEdit)
        <div class="flex flex-wrap gap-2">
            @unless($inReview)
                <button type="submit" form="editorial-form" @if($direct) name="publication_intent" value="{{ $published ? 'save' : 'draft' }}" @endif class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold dark:border-slate-600">{{ $published ? 'Save' : 'Save Draft' }}</button>
            @endunless
            @if($direct && !$published)
                <button type="submit" name="publication_intent" value="publish" class="rounded-lg bg-blue-700 px-3 py-2 text-xs font-bold text-white hover:bg-blue-800">Publish</button>
            @elseif($published && $direct)
                <button type="submit" name="publication_intent" value="draft" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold dark:border-slate-600">Move to Draft</button>
            @endif
            @if($editing && $reviewable && !$published && !$inReview)
                <button type="submit" form="editorial-review-form" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold dark:border-slate-600">Submit saved draft for review</button>
            @endif
        </div>
    @endif
    @if($editing)
        <div class="flex flex-wrap gap-x-4 gap-y-2 pt-1 text-xs font-semibold">
            <a href="{{ route('admin.editorial.preview', ['kind' => $kind, 'entry' => $entry]) }}" class="text-blue-700 underline dark:text-blue-300">Preview saved document</a>
            @if($published)<a href="{{ $entry->publicUrl() }}" class="text-blue-700 underline dark:text-blue-300">View published</a>@endif
            @if($kind === 'policies')<a href="{{ route('admin.policy-history.index', $entry) }}" class="text-blue-700 underline dark:text-blue-300">Version history</a>@endif
            @if($reviewable && !$published)<a href="{{ route('admin.editorial.reviews', ['kind' => $kind]) }}" class="text-blue-700 underline dark:text-blue-300">Review queue</a>@endif
        </div>
    @endif
</section>
