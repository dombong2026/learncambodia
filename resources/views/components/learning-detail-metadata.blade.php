@props(['record', 'kind'])

<div class="grid gap-4 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-[#152e50]">
        <h2 class="text-lg font-bold text-[#12355e] dark:text-white">{{ __('detail.requirement') }}</h2>
        <p class="mt-2 whitespace-pre-line break-words text-sm leading-6 text-slate-700 dark:text-slate-200">{{ filled($record->public_requirement) ? $record->public_requirement : __('detail.'.$kind.'_requirement') }}</p>
        @if($kind === 'course' && filled($record->cefr_level))
            <p class="mt-2 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ __('detail.suggested_level', ['level' => $record->cefr_level]) }}</p>
        @endif
    </section>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-[#152e50]">
        <h2 class="text-lg font-bold text-[#12355e] dark:text-white">{{ __('detail.'.$kind.'_language') }}</h2>
        <p class="mt-2 break-words text-sm leading-6 text-slate-700 dark:text-slate-200">{{ filled($record->content_language) ? $record->content_language : __('detail.language_unspecified') }}</p>
    </section>
</div>
