<aside id="course-outline" class="fl-outline fl-panel" aria-labelledby="outline-title">
    @php
        $total = $course->modules->sum(fn ($module) => $module->lessons->count());
        $numberDone = count($completedIds);
    @endphp
    <div class="fl-outline-heading">
        <span class="fl-section-icon"><x-workspace-icon name="layers" /></span>
        <div>
            <h2 id="outline-title">{{ __("focused_learning.outline") }}</h2>
            <p>{{ __('focused_learning.progress', ['done' => $numberDone, 'total' => $total]) }}</p>
        </div>
    </div>
    <progress class="fl-progress" aria-label="Course completion" max="{{ max(1, $total) }}" value="{{ $numberDone }}"></progress>
    <nav aria-label="Modules and lessons">
        @foreach($course->modules as $module)
            @php($quizCount = $module->lessons->filter(fn ($item) => filled($item->quiz_questions))->count())
            <details class="fl-module" @if($module->lessons->contains('id', $lesson->id)) open @endif>
                <summary>
                    <span class="fl-module-number">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                    <span>{{ $module->title }}<small>{{ trans_choice('focused_learning.lesson_count', $module->lessons->count()) }}@if($quizCount) · {{ trans_choice('focused_learning.quiz_count', $quizCount) }}@endif</small></span>
                    <x-workspace-icon name="chevron-down" class="fl-module-chevron" />
                </summary>
                <ol>
                    @foreach($module->lessons as $item)
                        @php($current = $item->id === $lesson->id)
                        <li>
                            <a @if($current && ($viewerKey ?? null) !== 'quiz') aria-current="page" @endif
                                href="{{ route('account.lessons.watch', [$enrollment, $item]) }}">
                                <span class="fl-lesson-dot">
                                    <x-workspace-icon :name="in_array($item->id, $completedIds, true) ? 'check' : 'book-open'" />
                                </span>
                                <span>{{ $item->title }}</span>
                                @if($item->duration_minutes > 0)<small>{{ $item->duration_minutes }}m</small>@endif
                            </a>
                        </li>
                        @if(filled($item->quiz_questions))
                            <li>
                                <a @if($current && ($viewerKey ?? null) === 'quiz') aria-current="page" @endif
                                    href="{{ route('account.lessons.watch', [$enrollment, $item, 'viewer' => 'quiz']) }}"
                                    aria-label="{{ __('focused_learning.quiz_for_lesson', ['lesson' => $item->title]) }}">
                                    <span class="fl-lesson-dot"><x-workspace-icon name="list-check" /></span>
                                    <span>{{ __('focused_learning.quiz') }}</span>
                                </a>
                            </li>
                        @endif
                    @endforeach
                </ol>
            </details>
        @endforeach
    </nav>
    @if($showCurriculumLink ?? false)
        <div class="fl-outline-return">
            <a class="fl-link" href="{{ route('account.enrollments.show', [$enrollment, 'view' => 'curriculum']) }}">{{ __('focused_learning.curriculum') }}</a>
        </div>
    @endif
</aside>
