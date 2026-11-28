@once
    <link rel="stylesheet" href="{{ asset('css/learning-reader.css') }}?v=3">
    <script src="{{ asset('js/learning-reader.js') }}?v=2" defer></script>
@endonce
@hasSection('learning-share')
    <div class="learning-reader-actions">
@endif
<div class="learning-reader-toolbar" data-reader-toolbar hidden>
    <div class="learning-reader-toolbar__group" role="group" aria-label="{{ __('learning_reader.zoom') }}">
        <button type="button" data-reader-decrease aria-label="{{ __('learning_reader.smaller') }}" title="{{ __('learning_reader.smaller') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="2" aria-hidden="true"><path d="M5 12h14"/></svg>
        </button>
        <button type="button" data-reader-reset aria-live="polite" title="{{ __('learning_reader.reset') }}" aria-label="{{ __('learning_reader.reset') }}">100%</button>
        <button type="button" data-reader-increase aria-label="{{ __('learning_reader.larger') }}" title="{{ __('learning_reader.larger') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="2" aria-hidden="true"><path d="M5 12h14m-7-7v14"/></svg>
        </button>
    </div>
</div>
@hasSection('learning-share')
        @yield('learning-share')
    </div>
@endif
