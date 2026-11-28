@extends('layouts.public')
@section('title', $item->title.' · Library Reader')
@push('public_styles')
    <link rel="stylesheet" href="{{ asset('css/student-library.css?v=1') }}">
@endpush
@section('content')
<div class="lc-library">
    <section class="lc-lib-frame lc-lib-section lc-lib-reader" data-reader data-format="{{ $item->format() }}"
        data-source="{{ $source }}" data-pdf-root="{{ asset('library-pdf/6.5.438') }}/"
        @if(isset($accessUntil)) data-access-until="{{ $accessUntil }}" @endif>
        <div class="lc-lib-head lc-lib-reader-header">
            <div>
                <a class="lc-lib-back" href="{{ $back }}">Back to item</a>
                <h1>{{ $item->title }}</h1>
            </div>
            @if($download)<a class="lc-lib-button" href="{{ $download }}">Download {{ $item->format() }}</a>@endif
        </div>
        <div class="lc-lib-reader-tools" data-reader-tools hidden role="toolbar" aria-label="Reader controls">
            @if($item->format() === 'PDF')
                <div class="lc-lib-control-group">
                    <button class="lc-lib-button" type="button" data-page-prev>Previous</button>
                    <label class="sr-only" for="reader-page">Page number</label>
                    <input class="lc-lib-page-field" type="number" id="reader-page" data-page min="1" value="1">
                    <span data-page-count></span>
                    <button class="lc-lib-button" type="button" data-page-next>Next</button>
                </div>
            @endif
            <div class="lc-lib-control-group">
                <button class="lc-lib-button" type="button" data-zoom-out aria-label="Zoom out">−</button>
                <output data-zoom aria-live="polite" aria-label="Zoom level">100%</output>
                <button class="lc-lib-button" type="button" data-zoom-in aria-label="Zoom in">+</button>
                <button class="lc-lib-button" type="button" data-zoom-reset>{{ $item->format() === 'PDF' ? 'Fit width' : 'Reset' }}</button>
            </div>
        </div>
        <p class="lc-lib-message" role="status" data-reader-status>Opening {{ $item->format() }}…</p>
        <noscript><p class="lc-lib-message">Enable JavaScript to use the online reader.</p></noscript>
        <div class="lc-lib-reader-stage" data-reader-stage hidden tabindex="0" aria-label="Reading area">
            @if($item->format() === 'PDF')
                <div class="lc-lib-pdf-page" data-pdf-page>
                    <canvas aria-label="Current PDF page"></canvas>
                    <div class="lc-lib-pdf-text sr-only" data-pdf-text></div>
                </div>
            @else
                <pre class="lc-lib-reader-text" data-reader-text></pre>
            @endif
        </div>
    </section>
</div>
<script type="module" src="{{ asset('js/student-library-reader.js?v=1') }}"></script>
@endsection
