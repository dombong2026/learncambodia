@props(['article' => null, 'title' => null, 'summary' => null, 'href' => null, 'image' => null, 'action' => null, 'contain' => false])

@php
    $imageAlt = '';
    if ($article) {
        $href = route('articles.show', $article->getRouteKey());
        $title = $article->title;
        $summary = $article->summary;
        $action = __('frontend.read_article');
        $image = asset('images/home/articles.webp');
        if ($article->featured_kind === 'image' && $article->featuredMedia) {
            $image = route('editor-media.show', $article->featuredMedia);
            $imageAlt = $article->featured_alt ?? '';
        }
    }
@endphp

<a class="article-tile" href="{{ $href }}" aria-label="{{ $title }}">
    <div @class(['article-tile-cover', 'article-tile-cover--contain' => $contain])>
        <img src="{{ $image }}" alt="{{ $imageAlt }}" width="960" height="540" loading="lazy" decoding="async">
    </div>
    <div class="article-tile-body">
        <h3>{{ $title }}</h3>
        @if($summary)<p>{{ $summary }}</p>@endif
        <span class="article-tile-action">
            {{ $action }}
            <x-workspace-icon name="chevron-right" aria-hidden="true"/>
        </span>
    </div>
</a>
