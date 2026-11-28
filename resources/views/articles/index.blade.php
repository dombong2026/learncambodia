@extends('layouts.public')
@section('title', \App\Support\Appearance::value('blog', 'hero_heading'))
@push('public_styles')
    <link rel="stylesheet" href="{{ asset('css/article-directory.css?v=20261121-2') }}">
@endpush
@section('content')
@php
    $a = \App\Support\Appearance::approved('blog');
    $index = route('articles.index', array_filter(['lang' => app()->getLocale(), 'category' => $category?->slug]));
    $featured = $category === null && $search === '' && $articles->onFirstPage() && $articles->count() > 4
        ? $articles->getCollection()->take(4)
        : collect();
@endphp
<div class="article-landing">
    <section class="article-hero" aria-labelledby="articles-heading">
        <div class="article-frame article-hero-inner">
            <div>
                <h1 id="articles-heading">{{ $a['hero_heading'] }}</h1>
                <p>{{ $a['hero_intro'] }}</p>
                <div class="article-hero-actions">
                    <a class="article-hero-link" href="#article-directory">Explore published articles</a>
                    <span>{{ number_format($publishedCount) }} published articles</span>
                </div>
            </div>
            <img class="article-hero-image" src="{{ asset('images/articles/student-reading.webp') }}" width="719" height="780"
                alt="Student wearing glasses and reading a paperback" fetchpriority="high" decoding="async">
        </div>
    </section>

    <section id="article-directory" class="article-section" aria-labelledby="article-directory-heading">
        <div class="article-frame">
            <div class="article-directory-toolbar">
                <h2 id="article-directory-heading">{{ $featured->isNotEmpty() ? $a['feature_heading'] : $a['articles_heading'] }}</h2>
                <form action="{{ route('articles.index') }}#article-directory" method="GET" role="search" class="article-search">
                    <input type="hidden" name="lang" value="{{ app()->getLocale() }}">
                    @if($category)<input type="hidden" name="category" value="{{ $category->slug }}">@endif
                    <label for="blog-search" class="sr-only">Search published articles</label>
                    <input id="blog-search" name="q" type="search" maxlength="80" value="{{ $search }}" placeholder="Search articles">
                    <button type="submit">Search</button>
                    @if($search !== '')<a href="{{ $index }}#article-directory">Clear</a>@endif
                </form>
            </div>

            @if($featured->isNotEmpty())
                <div class="article-card-grid">
                    @foreach($featured as $article)
                        <x-article-directory-card :article="$article"/>
                    @endforeach
                </div>
                <h2 class="article-section-subhead">{{ $a['articles_heading'] }}</h2>
            @endif

            <div class="article-card-grid">
                @forelse($articles->getCollection()->skip($featured->count()) as $article)
                    <x-article-directory-card :article="$article"/>
                @empty
                    <p class="article-empty">No published articles match your search. <a href="{{ $index }}#article-directory">Browse articles</a>.</p>
                @endforelse
            </div>
            <div class="article-pagination">{{ $articles->fragment('article-directory')->links() }}</div>
        </div>
    </section>

    <section class="article-section article-section-alt">
        <div class="article-frame">
            <h2>{{ $a['resources_heading'] }}</h2>
            <div class="article-card-grid">
                <x-article-directory-card title="Reviewed courses" summary="Browse approved courses and their learning outlines."
                    :image="asset('images/home/courses.webp')" :href="route('courses.index')" action="Browse courses"/>
                <x-article-directory-card title="CEFR learning levels" summary="See what learners can do at each level from A1 to C2."
                    :image="asset('images/home/levels.webp')" :href="route('cefr.index')" action="Explore levels"/>
                <x-article-directory-card title="Education contributors" summary="Meet the people who write and contribute to the articles."
                    :image="asset('images/home/educators.webp')" :href="route('authors.index')" action="Meet contributors"/>
            </div>
        </div>
    </section>

    <section class="article-section">
        <div class="article-frame">
            <h2>Questions about educational articles</h2>
            <div class="article-faq-grid">
                @foreach([
                    ['Who publishes articles?', 'Learn Cambodia administrators manage articles and their publication status.'],
                    ['Are article downloads or social groups available?', 'Only public text articles and links shown as available are supported. Unverified PDF downloads, messaging groups and memberships are not offered.'],
                    ['Can I submit a learning question?', 'You can use the protected inquiry form to contact the platform team. Do not include passwords or private records.'],
                    ['Do articles award qualifications?', 'No. Public education articles do not provide official test scores, certification or institutional accreditation.'],
                ] as [$question, $answer])
                    <details>
                        <summary>{{ $question }} <x-workspace-icon name="chevron-down" aria-hidden="true"/></summary>
                        <p>{{ $answer }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    <section class="article-section article-section-alt">
        <div class="article-frame">
            <h2>{{ $a['cta_heading'] }}</h2>
            <div class="article-card-grid">
                <x-article-directory-card title="Reading and literacy" summary="Find reading resources and practical study habits."
                    :image="asset('images/courses/course-catalog-hero.webp')" :href="route('learning.reading')" action="Explore reading" :contain="true"/>
                <x-article-directory-card title="Digital learning library" summary="Discover reviewed articles, course outlines and learning frameworks."
                    :image="asset('images/home/quizzes.webp')" :href="route('learning.library')" action="Open library"/>
            </div>
        </div>
    </section>
</div>
@endsection
