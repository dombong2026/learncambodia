@props(['items', 'label' => 'Library pages', 'jumpQuery' => null])

@if($items->hasPages() || ($jumpQuery !== null && $items->total() > 0))
    <nav class="lc-library-pages" aria-label="{{ $label }}">
        @if($items->previousPageUrl())
            <a class="lc-lib-button" rel="prev" href="{{ $items->previousPageUrl() }}">Previous</a>
        @else
            <span class="lc-lib-button" aria-disabled="true">Previous</span>
        @endif
        <span aria-current="page">Page {{ $items->currentPage() }} of {{ $items->lastPage() }}</span>
        @if($items->nextPageUrl())
            <a class="lc-lib-button" rel="next" href="{{ $items->nextPageUrl() }}">Next</a>
        @else
            <span class="lc-lib-button" aria-disabled="true">Next</span>
        @endif
        @if($jumpQuery !== null)
            <form method="get" action="{{ $items->path() }}#{{ $items->fragment() }}" class="lc-library-page-jump">
                @foreach($jumpQuery as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
                <label>Go to <input class="lc-lib-page-field" type="number" name="{{ $items->getPageName() }}" min="1" max="{{ $items->lastPage() }}" inputmode="numeric" value="{{ $items->currentPage() }}" aria-label="Go to page" required></label>
                <button class="lc-lib-button" type="submit">Go</button>
            </form>
        @endif
    </nav>
@endif
