@php
    $studyLinks = [
        ['courses.index', 'nav_courses', 'book-open', ['courses.show', 'learning.watch']],
        ['assessments.exams', 'nav_exams', 'clipboard-check', ['public.exams.show', 'specialized-exams.show', 'learning.exam*']],
        ['assessments.quizzes', 'nav_quizzes', 'quiz-bank', ['public.quizzes.show']],
    ];
@endphp
<nav class="public-mobile-nav hidden" aria-label="{{ __('frontend.study_navigation') }}">
    <div class="public-mobile-nav-items">
        @foreach($studyLinks as [$route, $label, $icon, $related])
            <a href="{{ route($route, ['lang' => app()->getLocale()]) }}" class="public-mobile-nav-link"
                @if(request()->routeIs($route)) aria-current="page" @elseif(request()->routeIs(...$related)) aria-current="true" @endif>
                <x-workspace-icon :name="$icon" class="h-5 w-5" />
                <span>{{ __('frontend.'.$label) }}</span>
            </a>
        @endforeach
    </div>
</nav>
