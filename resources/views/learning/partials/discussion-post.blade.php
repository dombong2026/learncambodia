<div class="fl-post">
    <div class="fl-post-byline">
        <strong>{{ $post->author?->name ?? 'Former learner' }}</strong>
        <time datetime="{{ $post->created_at->toIso8601String() }}">{{ $post->created_at->timezone('Asia/Phnom_Penh')->format('M j, Y · H:i') }}</time>
    </div>
    <p class="fl-message-body">{{ $post->body }}</p>
    <div class="fl-reactions" aria-label="Comment reactions">
        @foreach(\App\Models\LessonComment::REACTIONS as $kind)
            @php
                $selected = (bool) $post->{'viewer_'.$kind};
            @endphp
            <form method="POST" action="{{ route('account.lessons.react', [$enrollment, $lesson, $post]) }}">
                @csrf
                <input type="hidden" name="viewer" value="{{ $viewerKey }}">
                <input type="hidden" name="kind" value="{{ $kind }}">
                <input type="hidden" name="active" value="{{ $selected ? 0 : 1 }}">
                <button class="fl-reaction" type="submit" aria-pressed="{{ $selected ? 'true' : 'false' }}">
                    {{ __('focused_learning.'.$kind) }} <span>{{ $post->{$kind.'_count'} }}</span>
                </button>
            </form>
        @endforeach
    </div>
</div>
