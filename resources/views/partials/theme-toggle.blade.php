{{-- One accessible, local-only light/dark preference, shared by guest and member profiles. --}}
<div class="{{ ($showDivider ?? true) ? 'mt-1 border-t border-slate-200 pt-1 dark:border-slate-700' : 'mt-1 pt-1' }}">
    <button type="button" data-theme-toggle role="switch" aria-checked="false"
        aria-label="{{ __('ui.dark_mode') }}"
        class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-semibold text-slate-700 transition hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500 dark:text-slate-200 dark:hover:bg-slate-800">
        <svg aria-hidden="true" class="h-[18px] w-[18px] shrink-0 text-amber-600 dark:text-amber-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20.9 13A9 9 0 0 1 11 3.1 9 9 0 1 0 20.9 13Z"/>
        </svg>
        <span class="min-w-0 flex-1">{{ __('ui.dark_mode') }}</span>
        <span aria-hidden="true" class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full bg-slate-300 transition-colors dark:bg-blue-600">
            <span class="ml-0.5 h-4 w-4 rounded-full bg-white shadow-sm transition-transform dark:translate-x-4"></span>
        </span>
    </button>
</div>
