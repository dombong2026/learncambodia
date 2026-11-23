<section class="lc-cph" data-home-course-hero aria-labelledby="lc-cph-heading">
    <div class="lc-home-wrap">
        <header class="lc-cph-heading">
            <div>
                <p class="lc-cph-eyebrow">{{ __('home_hero.eyebrow') }}</p>
                <h1 id="lc-cph-heading">{{ __('home_hero.heading') }}</h1>
                <p class="lc-cph-intro">{{ __('home_hero.intro') }}</p>
            </div>
            <a class="lc-cph-outline-button" href="{{ route('courses.index') }}">{{ __('home_hero.browse_all') }}</a>
        </header>

        <div class="lc-cph-grid">
            <div class="lc-cph-stage" aria-live="polite">
                @foreach($heroCourses as $course)
                    <article class="lc-cph-screen" data-home-course-screen="{{ $course->id }}" @if(!$loop->first) hidden @endif>
                        <div class="lc-cph-media-frame">
                            <div class="lc-cph-media" data-home-course-media>
                                @if($course->featured_kind === 'video' && $course->featuredMedia)
                                    <video controls playsinline preload="none" aria-label="{{ $course->title }}">
                                        <source src="{{ route('editor-media.show', $course->featuredMedia) }}" type="{{ $course->featuredMedia->mime }}">
                                    </video>
                                @elseif($course->featured_kind === 'youtube' && preg_match('/^[A-Za-z0-9_-]{11}$/D', $course->featured_value ?? ''))
                                    <iframe loading="lazy" title="{{ $course->title }}" src="https://www.youtube-nocookie.com/embed/{{ $course->featured_value }}" referrerpolicy="strict-origin-when-cross-origin" allow="encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>
                                @elseif($course->featured_kind === 'image' && $course->featuredMedia)
                                    <img loading="{{ $loop->first ? 'eager' : 'lazy' }}" decoding="async" src="{{ route('editor-media.show', $course->featuredMedia) }}" alt="{{ $course->featured_alt ?: $course->title }}">
                                @else
                                    <div class="lc-cph-placeholder" @if($course->featured_kind === 'color' && preg_match('/^#[a-f0-9]{6}$/iD', $course->featured_value ?? '')) style="background-color: {{ $course->featured_value }}" @endif>
                                        <span class="lc-cph-placeholder-mark" aria-hidden="true">✦</span>
                                        <strong>{{ $course->title }}</strong>
                                    </div>
                                @endif
                            </div>
                            @foreach($course->modules as $module)
                                @foreach($module->lessons as $lesson)
                                    @if($lesson->is_preview)
                                        <div class="lc-cph-preview" data-home-lesson-preview="{{ $course->id }}:{{ $lesson->id }}" hidden>
                                            <span class="lc-cph-preview-label">{{ __('home_hero.public_preview') }}</span>
                                            <h3>{{ $lesson->title }}</h3>
                                            <p class="lc-cph-preview-module">{{ $module->title }}</p>
                                            <div class="lc-cph-preview-body">{{ trim(\App\Support\EditorHtml::courseText($lesson->content_html)) ?: $lesson->content }}</div>
                                            <a class="lc-cph-outline-button" href="{{ route('courses.show', $course->getRouteKey()) }}">{{ __('home_hero.view_course') }}</a>
                                        </div>
                                    @endif
                                @endforeach
                            @endforeach
                        </div>
                        <div class="lc-cph-course-meta">
                            <div>
                                <h2>{{ $course->title }}</h2>
                                @if($course->summary)<p>{{ $course->summary }}</p>@endif
                            </div>
                            <a class="lc-cph-primary-button" href="{{ route('courses.show', $course->getRouteKey()) }}">{{ __('home_hero.view_course') }}</a>
                        </div>
                    </article>
                @endforeach
            </div>

            <aside class="lc-cph-sidebar" aria-label="{{ __('home_hero.sidebar') }}">
                <div data-home-course-list>
                    <div class="lc-cph-sidebar-header">
                        <h2>{{ __('home_hero.choose_course') }}</h2>
                        <p>{{ __('home_hero.choose_hint') }}</p>
                    </div>
                    <nav class="lc-cph-course-list" aria-label="{{ __('home_hero.choose_course') }}">
                        @foreach($heroCourses as $course)
                            <a data-home-course-choice="{{ $course->id }}" href="{{ route('courses.show', $course->getRouteKey()) }}" @if($loop->first) aria-current="true" @endif>
                                <span>{{ $course->title }}</span>
                                @if($course->cefr_level)<small>CEFR {{ $course->cefr_level }}</small>@endif
                            </a>
                        @endforeach
                    </nav>
                </div>

                @foreach($heroCourses as $course)
                    <div data-home-course-outline="{{ $course->id }}" hidden>
                        <div class="lc-cph-sidebar-header">
                            <button type="button" class="lc-cph-back" data-home-course-back>← {{ __('home_hero.all_courses') }}</button>
                            <h2>{{ $course->title }}</h2>
                            <p>{{ __('home_hero.modules_and_lessons') }}</p>
                        </div>
                        <div class="lc-cph-outline">
                            @forelse($course->modules as $module)
                                <details class="lc-cph-module" @if($loop->first) open @endif>
                                    <summary>{{ $module->title }}</summary>
                                    <ul>
                                        @foreach($module->lessons as $lesson)
                                            <li>
                                                <a href="{{ route('courses.show', $course->getRouteKey()) }}#course-curriculum" @if($lesson->is_preview) data-home-lesson-choice="{{ $course->id }}:{{ $lesson->id }}" @endif>
                                                    <span>{{ $lesson->title }}</span>
                                                    <small>{{ $lesson->is_preview ? __('home_hero.public_preview') : __('home_hero.enrollment_required') }}</small>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </details>
                            @empty
                                <p class="lc-cph-empty">{{ __('home_hero.no_lessons') }}</p>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </aside>
        </div>
    </div>
</section>
