<section data-home-course-hero class="border-b border-white/10 bg-gradient-to-br from-[#071a36] via-[#12365d] to-[#0a294c] py-10 text-white sm:py-14">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div class="min-w-0">
                <p class="text-xs font-bold uppercase tracking-widest text-[#f2c36e]">{{ __('home_hero.eyebrow') }}</p>
                <h1 class="mt-2 text-2xl font-black leading-tight sm:text-4xl">{{ __('home_hero.heading') }}</h1>
                <p class="mt-2 max-w-3xl text-sm leading-7 text-[#cedff1]">{{ __('home_hero.intro') }}</p>
            </div>
            <a href="{{ route('courses.index') }}" class="rounded-xl border border-[#e9ba67] px-4 py-2.5 text-xs font-bold text-[#ffde99] transition hover:bg-white/10">{{ __('home_hero.browse_all') }}</a>
        </div>

        <div class="grid min-w-0 gap-5 lg:grid-cols-[minmax(0,1.7fr)_minmax(285px,.85fr)]">
            <div class="min-w-0 overflow-hidden rounded-2xl border border-white/15 bg-[#06182d] shadow-2xl">
                @foreach($heroCourses as $course)
                    <article data-home-course-screen="{{ $course->id }}" @if(!$loop->first) hidden @endif class="min-w-0">
                        <div class="relative aspect-video overflow-hidden bg-[#071324]">
                            <div data-home-course-media class="absolute inset-0 flex items-center justify-center">
                                @if($course->featured_kind === 'video' && $course->featuredMedia)
                                    <video controls preload="none" playsinline class="h-full w-full object-contain" aria-label="{{ $course->title }}">
                                        <source src="{{ route('editor-media.show', $course->featuredMedia) }}" type="{{ $course->featuredMedia->mime }}">
                                    </video>
                                @elseif($course->featured_kind === 'youtube' && preg_match('/^[A-Za-z0-9_-]{11}$/D', $course->featured_value ?? ''))
                                    <iframe class="h-full w-full" loading="lazy" title="{{ $course->title }}" src="https://www.youtube-nocookie.com/embed/{{ $course->featured_value }}" referrerpolicy="strict-origin-when-cross-origin" allow="encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>
                                @elseif($course->featured_kind === 'image' && $course->featuredMedia)
                                    <img class="h-full w-full object-contain" loading="{{ $loop->first ? 'eager' : 'lazy' }}" decoding="async" src="{{ route('editor-media.show', $course->featuredMedia) }}" alt="{{ $course->featured_alt ?: $course->title }}">
                                @else
                                    <div class="flex h-full w-full flex-col items-center justify-center gap-3 p-6 text-center" @if($course->featured_kind === 'color' && preg_match('/^#[a-f0-9]{6}$/iD', $course->featured_value ?? '')) style="background-color: {{ $course->featured_value }}" @endif>
                                        <x-workspace-icon name="graduation-cap" class="h-14 w-14 text-[#f4c878]" />
                                        <p class="max-w-md text-xl font-black sm:text-3xl">{{ $course->title }}</p>
                                    </div>
                                @endif
                            </div>
                            @foreach($course->modules as $module)
                                @foreach($module->lessons as $lesson)
                                    @if($lesson->is_preview)
                                        <div data-home-lesson-preview="{{ $course->id }}:{{ $lesson->id }}" hidden class="absolute inset-0 overflow-y-auto bg-[#081c35] p-5 sm:p-8">
                                            <p class="text-xs font-bold uppercase tracking-wider text-[#f1c573]">{{ __('home_hero.public_preview') }}</p>
                                            <h3 class="mt-2 text-lg font-black sm:text-2xl">{{ $lesson->title }}</h3>
                                            <p class="mt-1 text-xs text-[#b2cce4]">{{ $module->title }}</p>
                                            <p class="mt-5 whitespace-pre-line break-words text-sm leading-7 text-[#e5effb]">{{ trim(\App\Support\EditorHtml::courseText($lesson->content_html)) ?: $lesson->content }}</p>
                                            <a href="{{ route('courses.show', $course->getRouteKey()) }}" class="mt-6 inline-flex rounded-lg border border-[#f0c575] px-4 py-2.5 text-xs font-bold text-[#f7d995]">{{ __('home_hero.view_course') }}</a>
                                        </div>
                                    @endif
                                @endforeach
                            @endforeach
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-4 border-t border-white/10 px-4 py-4 sm:px-5">
                            <div class="min-w-0">
                                <h2 class="break-words text-base font-extrabold sm:text-lg">{{ $course->title }}</h2>
                                <p class="mt-1 line-clamp-2 text-xs leading-6 text-[#b9d0e5]">{{ $course->summary }}</p>
                            </div>
                            <a href="{{ route('courses.show', $course->getRouteKey()) }}" class="shrink-0 rounded-lg bg-[#f1bf63] px-4 py-2.5 text-xs font-extrabold text-[#0a2d51] hover:bg-[#ffda96]">{{ __('home_hero.view_course') }}</a>
                        </div>
                    </article>
                @endforeach
            </div>

            <aside class="min-w-0 overflow-hidden rounded-2xl border border-white/15 bg-[#112c4e]/95 shadow-xl" aria-label="{{ __('home_hero.sidebar') }}">
                <div data-home-course-list>
                    <div class="border-b border-white/10 px-5 py-4">
                        <h2 class="text-lg font-black">{{ __('home_hero.choose_course') }}</h2>
                        <p class="mt-1 text-xs leading-6 text-[#aec6dd]">{{ __('home_hero.choose_hint') }}</p>
                    </div>
                    <nav class="max-h-[480px] space-y-1 overflow-y-auto p-3" aria-label="{{ __('home_hero.choose_course') }}">
                        @foreach($heroCourses as $course)
                            <a data-home-course-choice="{{ $course->id }}" @if($loop->first) aria-current="true" @endif href="{{ route('courses.show', $course->getRouteKey()) }}" class="block rounded-xl border border-transparent px-4 py-3 text-sm font-semibold leading-6 text-[#d9e7f6] transition hover:border-[#edc16f]/60 hover:bg-[#21496f] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#f3c26b]">
                                {{ $course->title }}
                                @if($course->cefr_level)<span class="ml-1 text-xs text-[#f6d48f]">CEFR {{ $course->cefr_level }}</span>@endif
                            </a>
                        @endforeach
                    </nav>
                </div>

                @foreach($heroCourses as $course)
                    <div data-home-course-outline="{{ $course->id }}" hidden>
                        <div class="border-b border-white/10 px-5 py-4">
                            <button type="button" data-home-course-back class="mb-3 text-xs font-bold text-[#f7d18a] hover:underline">← {{ __('home_hero.all_courses') }}</button>
                            <h2 class="text-base font-black leading-6">{{ $course->title }}</h2>
                            <p class="mt-1 text-xs text-[#b0c8dd]">{{ __('home_hero.modules_and_lessons') }}</p>
                        </div>
                        <div class="max-h-[480px] overflow-y-auto p-3">
                            @forelse($course->modules as $module)
                                <details class="mb-2 rounded-xl border border-white/10 bg-[#0a213d]" @if($loop->first) open @endif>
                                    <summary class="px-4 py-3 text-sm font-bold leading-6 text-white">{{ $module->title }}</summary>
                                    <ul class="space-y-1 border-t border-white/10 p-2">
                                        @foreach($module->lessons as $lesson)
                                            <li>
                                                <a href="{{ route('courses.show', $course->getRouteKey()) }}#course-curriculum"
                                                   @if($lesson->is_preview) data-home-lesson-choice="{{ $course->id }}:{{ $lesson->id }}" @endif
                                                   class="block rounded-lg px-3 py-2 text-xs leading-5 text-[#d1e2f4] transition hover:bg-[#21496f] focus-visible:outline-2 focus-visible:outline-[#f3c26b]">
                                                    <span class="block font-semibold">{{ $lesson->title }}</span>
                                                    <span class="mt-1 block text-[11px] text-[#e9c480]">{{ $lesson->is_preview ? __('home_hero.public_preview') : __('home_hero.enrollment_required') }}</span>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </details>
                            @empty
                                <p class="px-3 py-5 text-sm text-[#c4d8ec]">{{ __('home_hero.no_lessons') }}</p>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </aside>
        </div>
    </div>
</section>
