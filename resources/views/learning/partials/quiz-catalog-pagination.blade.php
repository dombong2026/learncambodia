@if($paginator->hasPages())
    @php
        $first = $paginator->firstItem();
        $last = $paginator->lastItem();
    @endphp
    <nav class="quiz-catalog-pagination" aria-label="Quiz pages">
        @if($first)
            <p class="quiz-catalog-pagination__summary">
                Showing {{ $first === $last ? $first : "{$first}–{$last}" }} of {{ $paginator->total() }} quizzes
            </p>
        @endif
        <div class="quiz-catalog-pagination__controls">
            @if($paginator->previousPageUrl())
                <a class="quiz-catalog-pagination__button" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
            @else
                <span class="quiz-catalog-pagination__button" aria-disabled="true">Previous</span>
            @endif

            <span class="quiz-catalog-pagination__compact" aria-current="page">
                Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}
            </span>
            <ol class="quiz-catalog-pagination__pages">
                @foreach($elements as $element)
                    @if(is_string($element))
                        <li aria-hidden="true">{{ $element }}</li>
                    @else
                        @foreach($element as $page => $url)
                            <li>
                                @if($page === $paginator->currentPage())
                                    <span class="quiz-catalog-pagination__button" aria-current="page" aria-label="Page {{ $page }}">{{ $page }}</span>
                                @else
                                    <a class="quiz-catalog-pagination__button" href="{{ $url }}" aria-label="Go to page {{ $page }}">{{ $page }}</a>
                                @endif
                            </li>
                        @endforeach
                    @endif
                @endforeach
            </ol>

            @if($paginator->nextPageUrl())
                <a class="quiz-catalog-pagination__button" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
            @else
                <span class="quiz-catalog-pagination__button" aria-disabled="true">Next</span>
            @endif
        </div>
    </nav>
@endif
