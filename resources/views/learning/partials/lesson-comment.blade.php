<h2>{{ __("focused_learning.discussion") }}</h2>
<p class="fl-muted">{{ __("focused_learning.visible_comments") }}</p>
<form class="fl-form" action="{{ route('account.lessons.comment', [$enrollment, $lesson]) }}" method="POST">
    @csrf
    <input type="hidden" name="viewer" value="{{ $viewerKey }}">
    <label for="lesson-comment">{{ __("focused_learning.write_comment") }}</label>
    <textarea id="lesson-comment" name="comment" rows="3" maxlength="4000" required>{{ old('parent_id') ? '' : old('comment') }}</textarea>
    @if(!old('parent_id')) @error('comment')<p class="fl-error" role="alert">{{ $message }}</p>@enderror @endif
    <button type="submit" class="fl-button fl-primary">{{ __("focused_learning.post_comment") }}</button>
</form>
<div class="fl-discussion">
    @forelse($comments as $comment)
        <article id="thread-{{ $comment->id }}" class="fl-thread">
            @include('learning.partials.discussion-post', ['post' => $comment])
            @if($comment->replies->isNotEmpty())
                <div class="fl-replies" aria-label="Replies">
                    @foreach($comment->replies as $reply)
                        @include('learning.partials.discussion-post', ['post' => $reply])
                    @endforeach
                </div>
            @endif
            <details class="fl-reply" @if((int) old('parent_id') === $comment->id) open @endif>
                <summary>{{ __("focused_learning.reply") }}</summary>
                <form class="fl-form" action="{{ route('account.lessons.comment', [$enrollment, $lesson]) }}" method="POST">
                    @csrf
                    <input type="hidden" name="viewer" value="{{ $viewerKey }}">
                    <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                    <label for="reply-{{ $comment->id }}">{{ __("focused_learning.write_reply") }}</label>
                    <textarea id="reply-{{ $comment->id }}" name="comment" rows="2" maxlength="4000" required>{{ (int) old('parent_id') === $comment->id ? old('comment') : '' }}</textarea>
                    @if((int) old('parent_id') === $comment->id) @error('comment')<p class="fl-error" role="alert">{{ $message }}</p>@enderror @endif
                    <button type="submit" class="fl-button">{{ __("focused_learning.post_reply") }}</button>
                </form>
            </details>
        </article>
    @empty
        <p class="fl-muted">{{ __("focused_learning.no_comments") }}</p>
    @endforelse
    {{ $comments->links() }}
</div>
