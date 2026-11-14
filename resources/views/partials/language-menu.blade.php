{{-- Shared public + workspace language dropdown. Visible flag only; option labels stay readable. --}}
<div class="relative shrink-0" data-dropdown data-header-control>
    <button type="button" data-dropdown-trigger aria-expanded="false" aria-controls="locale-menu"
        aria-haspopup="true" aria-label="{{ __('ui.language') }}" title="{{ __('ui.language') }}"
        class="premium-header-trigger premium-language-trigger grid h-10 w-10 place-items-center rounded-full border border-transparent bg-transparent text-slate-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 dark:text-slate-100">
        <span class="premium-language-flag text-xl leading-none" aria-hidden="true">{{ ['en'=>'🇬🇧','km'=>'🇰🇭','zh'=>'🇨🇳'][app()->getLocale()] ?? '🇬🇧' }}</span>
    </button>
    <div id="locale-menu" data-dropdown-panel hidden role="group" aria-label="{{ __('ui.language') }}"
        class="premium-header-panel absolute right-0 z-50 mt-2 w-44 rounded-2xl border border-slate-200 bg-white p-1.5 shadow-xl shadow-slate-950/10 dark:border-slate-700 dark:bg-slate-900 dark:shadow-black/30">
        @foreach(['en'=>['🇬🇧','English'],'km'=>['🇰🇭','ភាសាខ្មែរ'],'zh'=>['🇨🇳','简体中文']] as $code => [$flag,$name])
            <form action="{{ route('language.update') }}" method="POST">
                @csrf
                <button type="submit" name="locale" value="{{ $code }}" aria-current="{{ app()->getLocale()===$code ? 'true' : 'false' }}"
                    class="premium-menu-option flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium text-slate-700 hover:text-blue-800 focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-blue-600 dark:text-slate-100 dark:hover:text-amber-200">
                    <span aria-hidden="true" class="text-base">{{ $flag }}</span><span>{{ $name }}</span>
                </button>
            </form>
        @endforeach
    </div>
</div>
