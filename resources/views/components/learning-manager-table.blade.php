@props(['items', 'name', 'measure' => 'Modules', 'empty' => 'No records have been created.', 'searchLabel' => 'Search by title, ID, category, or level'])
@php
    $search = \App\Support\LearningTable::search(request());
    $locale = request()->query('lang');
    $current = $items->currentPage();
    $last = $items->lastPage();
    $numbers = collect([1, $current - 1, $current, $current + 1, $last])
        ->filter(fn ($page) => $page > 0 && $page <= $last)->unique()->sort()->values();
@endphp
@if($error = $errors->first('manager'))
    <p class="learning-manager-error" role="alert">{{ $error }}</p>
@endif
<section class="learning-manager" aria-label="{{ $name }}">
    <div class="learning-manager-toolbar">
        <span class="learning-manager-count">{{ trans_choice('{0} No records|{1} :count record|[2,*] :count records', $items->total()) }}</span>
        <form method="get" action="{{ $items->path() }}" role="search" aria-label="{{ $searchLabel }}" class="learning-manager-search">
            @if(is_string($locale) && $locale !== '')<input type="hidden" name="lang" value="{{ $locale }}">@endif
            <label class="learning-manager-search-field">
                <span class="sr-only">{{ $searchLabel }}</span>
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 4 4" stroke-linecap="round"/></svg>
                <input type="search" name="q" value="{{ $search }}" maxlength="100" placeholder="{{ $searchLabel }}">
            </label>
            <button type="submit" class="learning-manager-control learning-manager-primary">Search</button>
            @if($search !== '')<a class="learning-manager-control" href="{{ $items->path().(is_string($locale) && $locale !== '' ? '?'.http_build_query(['lang' => $locale]) : '') }}">Clear</a>@endif
        </form>
    </div>
    <div class="learning-manager-scroll" tabindex="0" role="region" aria-label="{{ $name }} table">
        <table>
            <thead>
                <tr>
                    @foreach(['No', 'ID', $name, 'Category', 'Level', $measure, 'Status', 'Action'] as $heading)
                        <th scope="col">{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @if($items->isEmpty())
                    <tr><td class="learning-manager-empty" colspan="8">
                        @if($search === '' && isset($emptyState)){{ $emptyState }}
                        @else{{ $search === '' ? $empty : 'No records match your search.' }}@endif
                    </td></tr>
                @else
                    {{ $slot }}
                @endif
            </tbody>
        </table>
    </div>
    @if($items->total() > 0)
        <footer class="learning-manager-footer">
            <span>Showing {{ $items->firstItem() }}–{{ $items->lastItem() }} of {{ $items->total() }}</span>
            @if($items->hasPages())
                <nav class="learning-manager-pages" aria-label="Pagination">
                    @if($items->onFirstPage())<span class="learning-manager-control" aria-disabled="true">Previous</span>
                    @else<a rel="prev" class="learning-manager-control" href="{{ $items->previousPageUrl() }}">Previous</a>@endif
                    @foreach($numbers as $i => $number)
                        @if($i > 0 && $number > $numbers[$i - 1] + 1)<span aria-hidden="true">…</span>@endif
                        @if($number === $current)<span class="learning-manager-control learning-manager-current" aria-current="page">{{ $number }}</span>
                        @else<a class="learning-manager-control" href="{{ $items->url($number) }}" aria-label="Page {{ $number }}">{{ $number }}</a>@endif
                    @endforeach
                    @if($items->hasMorePages())<a rel="next" class="learning-manager-control" href="{{ $items->nextPageUrl() }}">Next</a>
                    @else<span class="learning-manager-control" aria-disabled="true">Next</span>@endif
                </nav>
                <form method="get" action="{{ $items->path() }}" class="learning-manager-goto">
                    @if($search !== '')<input type="hidden" name="q" value="{{ $search }}">@endif
                    @if(is_string($locale) && $locale !== '')<input type="hidden" name="lang" value="{{ $locale }}">@endif
                    <label>Go to <input type="number" min="1" max="{{ $last }}" name="page" required inputmode="numeric" aria-label="Page number" placeholder="{{ $current }}"></label>
                    <button type="submit" class="learning-manager-control">Go</button>
                </form>
            @endif
        </footer>
    @endif
</section>
