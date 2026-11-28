@props(['item', 'showOpenAccess' => true, 'showAuthorAvatar' => false])

@php
    $author = $showAuthorAvatar ? str($item->author ?? '')->squish() : str('');
    $authorId = $author->isNotEmpty() ? 'library-author-'.$item->public_id : null;
@endphp

<a class="lc-lib-card" href="{{ route('library.show', $item) }}" aria-label="{{ $item->title }}" @if($authorId) aria-describedby="{{ $authorId }}" @endif>
    <x-student-library-cover :item="$item"/>
    @if($authorId)
        <span class="lc-catalog-author">
            <span class="lc-catalog-author-avatar" aria-hidden="true">
                @if($author->lower()->remove(' ')->value() === str(\App\Support\PlatformBranding::shortName())->lower()->squish()->remove(' ')->value())
                    <img src="{{ \App\Support\PlatformBranding::asset('favicon-32')['url'] }}" alt="" width="24" height="24" loading="lazy" decoding="async">
                @else
                    {{ $author->explode(' ')->take(2)->map(fn ($word) => str($word)->substr(0, 1)->upper()->value())->implode('') }}
                @endif
            </span>
            <span class="lc-catalog-author-name" id="{{ $authorId }}" role="tooltip">{{ $author }}</span>
        </span>
    @endif
    <div class="lc-lib-card-body">
        <div class="lc-lib-meta">
            <span>{{ $item->format() }}</span>
            <span>{{ \App\Models\LibraryItem::KINDS[$item->kind] }}</span>
        </div>
        <h3>{{ $item->title }}</h3>
        @if($item->author && !$showAuthorAvatar)<p>{{ $item->author }}</p>@endif
        @if($showOpenAccess || $item->access !== 'open')
            <div class="lc-lib-card-foot">
                <span>{{ match($item->access) {
                    'loan' => isset($item->active_loans_count) && $item->active_loans_count >= $item->copies ? 'Checked out' : 'Borrow',
                    'students' => 'Students',
                    default => 'Open access',
                } }}</span>
                <x-workspace-icon name="chevron-right" aria-hidden="true"/>
            </div>
        @endif
    </div>
</a>
