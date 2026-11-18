<div class="fl-message-history" data-message-history data-latest="{{ $messages->first()?->id }}">
    @if($messages instanceof \Illuminate\Pagination\Paginator && $messages->hasPages())
        <nav class="fl-message-pages" aria-label="Conversation history">
            @if($messages->previousPageUrl())<a class="fl-link" href="{{ $messages->previousPageUrl() }}#lesson-section">{{ __("focused_learning.newer") }}</a>@endif
            @if($messages->nextPageUrl())<a class="fl-link" href="{{ $messages->nextPageUrl() }}#lesson-section">{{ __("focused_learning.older") }}</a>@endif
        </nav>
    @endif
    @forelse(collect($messages->items())->reverse() as $message)
        <article class="fl-message {{ $message->author_id === auth()->id() ? 'fl-message-mine' : '' }}">
            <div class="fl-post-byline">
                <strong>{{ $message->author_id === auth()->id() ? __('focused_learning.you') : ($message->author?->name ?? 'Former participant') }}</strong>
                <time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->timezone('Asia/Phnom_Penh')->format('M j · H:i') }}</time>
            </div>
            <p class="fl-message-body">{{ $message->body }}</p>
        </article>
    @empty
        <p class="fl-muted">{{ __("focused_learning.empty_messages") }}</p>
    @endforelse
</div>
