@switch($viewer['type'])
    @case('reading')
        <article class="fl-reader" aria-label="Lesson reading">
            @include('learning.partials.reading-content')
        </article>
        @break
    @case('video')
        <div class="fl-video">
            <video-player class="fl-video-player" data-focused-media>
                <video-skin>
                    <video controls playsinline preload="metadata" src="{{ $viewer['url'] }}" aria-label="{{ $viewer['label'] }}">
                        <a href="{{ $viewer['url'] }}">Open video</a>
                    </video>
                </video-skin>
            </video-player>
        </div>
        @break
    @case('youtube')
        <div class="fl-video">
            <iframe src="{{ $viewer['url'] }}" title="{{ $viewer['label'] }}"
                loading="lazy" allow="encrypted-media; picture-in-picture; fullscreen" allowfullscreen
                referrerpolicy="strict-origin-when-cross-origin"></iframe>
        </div>
        @break
    @case('audio')
        <div class="fl-audio">
            <span class="fl-audio-icon"><x-workspace-icon name="headphones" /></span>
            <h2>{{ $viewer['label'] }}</h2>
            <div class="fl-audio-player">
                <audio controls preload="metadata" src="{{ $viewer['url'] }}" aria-label="{{ $viewer['label'] }}">
                    <a href="{{ $viewer['url'] }}">Open audio</a>
                </audio>
            </div>
        </div>
        @break
    @case('pdf')
        <iframe class="fl-pdf" src="{{ $viewer['url'] }}" title="{{ $viewer['label'] }}" loading="lazy"></iframe>
        @break
    @case('document')
        <article class="fl-reader" aria-label="Document viewer">
            <h2>{{ $viewer['label'] }}</h2>
            @forelse($document as $paragraph)
                <p class="fl-reading-text">{{ $paragraph }}</p>
            @empty
                <p class="fl-muted">A preview is unavailable for this document.</p>
            @endforelse
            <a class="fl-link" href="{{ $viewer['url'] }}">Download original</a>
        </article>
        @break
    @case('quiz')
        <div class="fl-viewer-padding">
            <x-lesson-quiz :lesson="$lesson" :enrollment="$enrollment" :attempt="$quizAttempt" :retry="$quizRetry" :history="$quizHistory" />
        </div>
        @break
    @case('exam')
        @php
            $exam = $viewer['exam'];
            $active = $attempts->first(fn ($attempt) => !$attempt->submitted_at && $attempt->expires_at->isFuture());
        @endphp
        <article class="fl-reader">
            <h2>{{ $exam->title }}</h2>
            <p class="fl-muted">{{ $exam->time_limit }} minutes · {{ $attempts->count() }} / {{ $exam->attempt_limit }} attempts started</p>
            @if($exam->instructions_html)
                <x-lesson-content :lesson="(object) ['content_html' => $exam->instructions_html, 'content' => '']" class="fl-reading-text" />
            @endif
            @if($active)
                <a class="fl-button fl-primary" href="{{ route('account.exam-attempts.show', [$enrollment, $active, 'from_lesson' => $lesson->id]) }}">Continue attempt</a>
            @elseif($attempts->count() < $exam->attempt_limit)
                <form method="POST" action="{{ route('account.exam-attempts.start', [$enrollment, $exam, 'from_lesson' => $lesson->id]) }}">
                    @csrf
                    <button class="fl-button fl-primary" type="submit">Start {{ $exam->kind === 'quiz' ? 'quiz' : 'exam' }}</button>
                </form>
            @else
                <p class="fl-muted">All attempts have been used.</p>
            @endif
            @foreach($attempts->whereNotNull('submitted_at') as $attempt)
                <a class="fl-link" href="{{ route('account.exam-attempts.show', [$enrollment, $attempt, 'from_lesson' => $lesson->id]) }}">
                    {{ $attempt->score === null ? 'View submitted attempt' : 'View result · '.$attempt->score.'%' }}
                </a>
            @endforeach
        </article>
        @break
@endswitch
