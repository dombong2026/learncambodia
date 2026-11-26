@extends('layouts.public')
@section('title', __('pages.home_title'))
@section('meta_description', __('pages.home_intro'))
@push('public_styles')
<link rel="stylesheet" href="{{ asset('css/home-sunrise.css') }}">
@endpush
@section('content')
@php
    $homeCopy = \App\Support\Appearance::approved('home');
    $heroTitle = $homeCopy['hero_title'] === \App\Support\Appearance::DEFAULTS['home']['hero_title'] ? __('sunrise.hero_title') : $homeCopy['hero_title'];
    $heroIntro = $homeCopy['hero_intro'] === \App\Support\Appearance::DEFAULTS['home']['hero_intro'] ? __('sunrise.hero_intro') : $homeCopy['hero_intro'];
@endphp
<div class="sunrise" data-home-layout="sunrise">
    <section class="sr-hero">
        <div class="sr-wrap sr-hero-grid">
            <div class="sr-hero-copy">
                <p class="sr-kicker"><span aria-hidden="true">✳</span> {{ __('sunrise.eyebrow') }}</p>
                <h1>{{ $heroTitle }}</h1>
                <p class="sr-intro">{{ $heroIntro }}</p>
                <div class="sr-actions">
                    <a class="sr-button sr-primary" href="{{ auth()->check() ? route(auth()->user()->homeRouteName()) : route('courses.index') }}">{{ auth()->check() ? __('sunrise.workspace') : __('pages.browse_courses') }} <span aria-hidden="true">↗</span></a>
                    <a class="sr-button sr-outline" href="{{ route('cefr.index') }}">{{ __('pages.find_your_level') }}</a>
                </div>
                <form class="sr-search" action="{{ route('courses.index') }}" method="GET" role="search">
                    <label for="sunrise-search" class="sr-only">{{ __('frontend.search_courses') }}</label>
                    <input id="sunrise-search" name="q" type="search" maxlength="80" placeholder="{{ __('pages.hero_search') }}">
                    <button type="submit">{{ __('pages.search') }}</button>
                </form>
            </div>
            <div class="sr-landscape" aria-hidden="true">
                <svg viewBox="0 0 560 420" xmlns="http://www.w3.org/2000/svg" role="presentation">
                    <defs><linearGradient id="sr-sky" x2="0" y2="1"><stop stop-color="#f9d69c"/><stop offset="1" stop-color="#fff5dc"/></linearGradient><linearGradient id="sr-field" x2="0" y2="1"><stop stop-color="#8ca98a"/><stop offset="1" stop-color="#426950"/></linearGradient></defs>
                    <rect width="560" height="420" rx="30" fill="url(#sr-sky)"/>
                    <circle cx="390" cy="125" r="82" fill="#f9b85c"/><circle cx="390" cy="125" r="104" stroke="#f6b862" opacity=".45" fill="none"/>
                    <path d="M0 238 Q90 167 182 224 T360 213 T560 178 V420 H0Z" fill="#b8c7a0"/>
                    <path d="M0 290 Q100 209 224 264 T450 255 T560 231 V420 H0Z" fill="url(#sr-field)"/>
                    <path d="M0 344 Q126 270 258 331 T560 285 V420 H0Z" fill="#315c48"/>
                    <path d="M250 420 Q346 361 299 325 Q276 308 315 283 Q363 313 346 337 Q333 366 440 420Z" fill="#e5bd7d"/>
                    <path d="M76 308 Q85 258 85 205 M85 250 Q53 232 46 211 Q78 214 85 239 M85 238 Q108 211 130 209 Q122 239 85 251 M85 277 Q60 273 49 251 Q76 250 85 270" stroke="#244c39" stroke-width="5" fill="none" stroke-linecap="round"/>
                    <g transform="translate(93 244) rotate(-8)"><rect width="122" height="93" rx="8" fill="#fdf8ec"/><path d="M61 13 Q39 1 12 9 V74 Q35 67 61 80 Q87 67 110 74 V9 Q83 1 61 13 V80" stroke="#315c48" stroke-width="3" fill="none"/><path d="M23 26 H46 M23 36 H48 M73 26 H98 M73 36 H95" stroke="#91a78b" stroke-width="3" stroke-linecap="round"/></g>
                    <path d="M463 56 q8 -9 16 0 q8 -9 16 0 M503 81 q6 -7 12 0 q6 -7 12 0" stroke="#976a46" stroke-width="2.5" fill="none" stroke-linecap="round"/>
                </svg>
            </div>
        </div>
    </section>
    <div class="sr-wrap sr-stats">
        @foreach([[$publishedCourseCount,__('pages.published_courses'),'courses.index'],[$quizCount,__('sunrise.quizzes'),'assessments.quizzes'],[$examCount,__('sunrise.exams'),'assessments.exams'],[$publishedArticleCount,__('pages.published_articles'),'articles.index']] as [$count,$label,$route])
            <a href="{{ route($route) }}"><strong>{{ number_format($count) }}</strong><span>{{ $label }}</span></a>
        @endforeach
    </div>
    <section class="sr-wrap sr-section">
        <div class="sr-heading"><div><p class="sr-kicker">{{ __('sunrise.explore') }}</p><h2>{{ __('sunrise.choose_path') }}</h2></div></div>
        <div class="sr-grid sr-four">
            @foreach([['book-open','pages.courses','pages.flexible_learning_desc','courses.index'],['clipboard-check','sunrise.quizzes','sunrise.quiz_intro','assessments.quizzes'],['award','sunrise.exams','sunrise.exam_intro','assessments.exams'],['newspaper','pages.study_resources','sunrise.read_intro','articles.index']] as [$icon,$title,$description,$route])
                <a class="sr-tile" href="{{ route($route) }}"><span class="sr-icon"><x-workspace-icon :name="$icon" class="h-6 w-6"/></span><h3>{{ __($title) }}</h3><p>{{ __($description) }}</p><span class="sr-arrow" aria-hidden="true">↗</span></a>
            @endforeach
        </div>
    </section>
    <section class="sr-band">
        <div class="sr-wrap sr-section">
            <div class="sr-heading"><div><p class="sr-kicker">CEFR · A1–C2</p><h2>{{ __('pages.find_your_level') }}</h2><p>{{ __('pages.level_guidance_desc') }}</p></div><a class="sr-text-link" href="{{ route('cefr.index') }}">{{ __('pages.read_cefr') }} ↗</a></div>
            <div class="sr-levels">
                @foreach(['A1','A2','B1','B2','C1','C2'] as $index => $level)
                    <a class="sr-level" href="{{ route('cefr.index') }}#{{ $level }}"><strong>{{ $level }}</strong><span>{{ __('sunrise.level_'.$level) }}</span><span class="sr-level-track" aria-hidden="true"><i style="width:{{ ($index+1)*100/6 }}%"></i></span></a>
                @endforeach
            </div>
        </div>
    </section>
    <section class="sr-wrap sr-section">
        <div class="sr-heading"><div><p class="sr-kicker">{{ __('pages.approved_courses') }}</p><h2>{{ __('pages.start_a_course') }}</h2></div><a class="sr-text-link" href="{{ route('courses.index') }}">{{ __('pages.view_all') }} ↗</a></div>
        @if($featuredCategories->isNotEmpty())
            <div class="sr-chips">@foreach($featuredCategories as $category)<a href="{{ route('categories.show',$category) }}">{{ $category->name }}</a>@endforeach <a href="{{ route('categories.index') }}">{{ __('pages.view_all') }} ↗</a></div>
        @endif
        <div class="sr-grid sr-three">
            @forelse($featuredCourses as $course)<x-public-course-card :course="$course" />@empty<p>{{ __('frontend.no_courses') }}</p>@endforelse
        </div>
    </section>
    <section class="sr-band">
        <div class="sr-wrap sr-section">
            <div class="sr-heading"><div><p class="sr-kicker">{{ __('sunrise.practice') }}</p><h2>{{ __('sunrise.practice_title') }}</h2></div></div>
            <div class="sr-grid sr-two">
                @foreach([[$latestQuizzes,'sunrise.quizzes','assessments.quizzes','quiz'],[$latestExams,'sunrise.exams','assessments.exams','exam']] as [$items,$title,$route,$kind])
                    <section class="sr-assessment"><div class="sr-heading"><h3>{{ __($title) }}</h3><a class="sr-text-link" href="{{ route($route) }}">{{ __('pages.view_all') }} ↗</a></div>
                        @forelse($items as $item)
                            <a class="sr-assessment-row" href="{{ route('assessments.show',$item) }}"><span>{{ $item->title }}</span><span aria-hidden="true">↗</span></a>
                        @empty<p>{{ __('sunrise.no_assessments') }}</p>@endforelse
                    </section>
                @endforeach
            </div>
        </div>
    </section>
    @if($featuredInstructors->isNotEmpty())
    <section class="sr-wrap sr-section"><div class="sr-heading"><div><p class="sr-kicker">{{ __('pages.verified_profiles') }}</p><h2>{{ __('pages.featured_instructors') }}</h2></div><a class="sr-text-link" href="{{ route('instructors.index') }}">{{ __('pages.meet_instructors') }} ↗</a></div>
        <div class="sr-grid sr-four">@foreach($featuredInstructors as $profile)<x-public-person-card :profile="$profile" kind="instructor"/>@endforeach</div>
    </section>
    @endif
    <section class="sr-wrap sr-section"><div class="sr-heading"><div><p class="sr-kicker">{{ __('sunrise.small_steps') }}</p><h2>{{ __('pages.how_it_works') }}</h2></div></div>
        <div class="sr-grid sr-four">
            @foreach([['pages.step_discover','pages.step_discover_desc','courses.index'],['pages.step_account','pages.step_account_desc','register'],['pages.step_approval','pages.step_approval_desc',auth()->check() ? auth()->user()->homeRouteName() : 'login'],['pages.step_study','pages.step_study_desc','cefr.index']] as $i=>[$title,$desc,$route])
                <a class="sr-tile" href="{{ route($route) }}"><span class="sr-step">{{ str_pad((string)($i+1),2,'0',STR_PAD_LEFT) }}</span><h3>{{ __($title) }}</h3><p>{{ __($desc) }}</p></a>
            @endforeach
        </div>
    </section>
    @if($latestArticles->isNotEmpty())
    <section class="sr-wrap sr-section"><div class="sr-heading"><h2>{{ __('pages.study_resources') }}</h2><a class="sr-text-link" href="{{ route('articles.index') }}">{{ __('pages.view_all') }} ↗</a></div>
        <div class="sr-grid sr-three">@foreach($latestArticles as $article)<a class="sr-tile" href="{{ route('articles.show',$article->getRouteKey()) }}"><span class="sr-kicker">{{ __('frontend.study_resources') }}</span><h3>{{ $article->title }}</h3><p>{{ \Illuminate\Support\Str::limit(strip_tags($article->summary ?? ''), 150) }}</p><span class="sr-text-link">{{ __('frontend.read_article') }} ↗</span></a>@endforeach</div>
    </section>
    @endif
    <section class="sr-wrap sr-section"><div class="sr-cta"><div><p class="sr-kicker">{{ __('sunrise.eyebrow') }}</p><h2>{{ __('sunrise.cta') }}</h2><p>{{ __('sunrise.hero_intro') }}</p></div><a class="sr-button sr-primary" href="{{ auth()->check() ? route(auth()->user()->homeRouteName()) : route('register') }}">{{ auth()->check() ? __('sunrise.workspace') : __('pages.step_account') }} ↗</a></div></section>
</div>
@endsection
