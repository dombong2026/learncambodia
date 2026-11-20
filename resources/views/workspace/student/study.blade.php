@extends('layouts.workspace')
@section('title', $course->title)
@section('heading', $course->title)
@section('description', 'Browse your course curriculum, track completed lessons, and open a lesson to learn.')
@push('workspace_styles')
<style>
.lc-curriculum{--cc-surface:#fff;--cc-soft:#f5f8fd;--cc-border:#dce5f0;--cc-text:#17253b;--cc-muted:#63758f;--cc-accent:#1559e8;--cc-hover:#eff5ff;--cc-success:#087658;color:var(--cc-text)}
.dark .lc-curriculum{--cc-surface:#12243c;--cc-soft:#0d1b30;--cc-border:#30445f;--cc-text:#e8eef8;--cc-muted:#9daec6;--cc-accent:#91bdff;--cc-hover:#1a3555;--cc-success:#6ee7ba}
.lc-curriculum .cc-panel{background:var(--cc-surface);border:1px solid var(--cc-border);border-radius:18px;overflow:hidden}
.lc-curriculum .cc-toolbar{display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;margin-bottom:18px}
.lc-curriculum .cc-links{display:flex;flex-wrap:wrap;gap:9px;align-items:center}
.lc-curriculum .cc-button{display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:9px 14px;border-radius:10px;border:1px solid var(--cc-border);font-size:13px;font-weight:700;color:var(--cc-text);text-decoration:none;background:var(--cc-surface);transition:background .15s,border-color .15s}
.lc-curriculum .cc-button:hover,.lc-curriculum .cc-button:focus-visible{background:var(--cc-hover);border-color:var(--cc-accent)}
.lc-curriculum .cc-button-primary{background:#1655e7;color:white;border-color:#1655e7}
.lc-curriculum .cc-button-primary:hover,.lc-curriculum .cc-button-primary:focus-visible{background:#1144c2;color:white;border-color:#1144c2}
.lc-curriculum .cc-overview{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin-bottom:16px}
.lc-curriculum .cc-overview-card{display:flex;flex-direction:column;min-width:0;gap:14px;padding:clamp(16px,2vw,26px)}
.lc-curriculum .cc-learning-progress{background:linear-gradient(145deg,var(--cc-hover),var(--cc-surface) 70%)}
.lc-curriculum .cc-card-heading{display:flex;align-items:center;gap:10px;font-size:16px;font-weight:800}
.lc-curriculum .cc-card-icon{display:grid;flex:none;place-items:center;width:34px;height:34px;border-radius:10px;background:var(--cc-soft);color:var(--cc-accent)}
.lc-curriculum .cc-progress-value{color:var(--cc-accent);font-size:clamp(2.65rem,5vw,4.25rem);font-weight:820;letter-spacing:-.055em;line-height:1;font-variant-numeric:tabular-nums}
.lc-curriculum .cc-summary-title{margin-top:4px;font-size:14px;font-weight:750}
.lc-curriculum .cc-muted{color:var(--cc-muted);font-size:12px}
.lc-curriculum .cc-overview-card progress{display:block;width:100%;height:8px;appearance:none;border:0;border-radius:99px;background:var(--cc-border);overflow:hidden}
.lc-curriculum .cc-overview-card progress::-webkit-progress-bar{background:var(--cc-border);border-radius:99px}
.lc-curriculum .cc-overview-card progress::-webkit-progress-value{background:#3a77f0;border-radius:99px}
.lc-curriculum .cc-overview-card progress::-moz-progress-bar{background:#3a77f0;border-radius:99px}
.lc-curriculum .cc-saved-lesson{min-width:0;overflow-wrap:anywhere;font-size:13px;font-weight:700}
.lc-curriculum .cc-card-footer{margin-top:auto;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
.lc-curriculum .cc-about-body{max-height:9rem;overflow:auto;scrollbar-color:var(--cc-border) transparent;line-height:1.65;color:var(--cc-muted);font-size:13px;overflow-wrap:anywhere}
.lc-curriculum .cc-about-body:focus-visible{outline:2px solid var(--cc-accent);outline-offset:2px}
.lc-curriculum .cc-about-body img,.lc-curriculum .cc-about-body iframe{max-width:100%}
.lc-curriculum .cc-about-facts{margin-top:auto;display:flex;flex-wrap:wrap;gap:8px 18px;border-top:1px solid var(--cc-border);padding-top:13px;font-weight:700}
.lc-curriculum .cc-table-head{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:14px;padding:18px 20px;border-bottom:1px solid var(--cc-border)}
.lc-curriculum .cc-table-head h2{font-size:18px;font-weight:800}
.lc-curriculum .cc-search{display:flex;align-items:center;gap:7px;flex-wrap:wrap}
.lc-curriculum .cc-search input{min-width:220px;width:min(320px,65vw);background:var(--cc-soft);color:var(--cc-text);border:1px solid var(--cc-border);border-radius:10px;padding:10px 12px;font-size:13px}
.lc-curriculum .cc-search input:focus-visible{outline:2px solid var(--cc-accent);outline-offset:1px}
.lc-curriculum .cc-table{width:100%;border-collapse:collapse;text-align:left;table-layout:auto;font-size:13px}
.lc-curriculum .cc-table thead{background:var(--cc-soft)}
.lc-curriculum .cc-table thead th{font-size:11px;text-transform:uppercase;letter-spacing:.07em;color:var(--cc-muted);font-weight:800;padding:13px 14px}
.lc-curriculum .cc-table td{padding:11px 14px;border-top:1px solid var(--cc-border);vertical-align:middle}
.lc-curriculum .cc-table .cc-module th{padding:14px 16px;background:var(--cc-hover);border-top:1px solid var(--cc-border);font-size:13px;font-weight:800}
.lc-curriculum .cc-module-heading{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
.lc-curriculum .cc-module-name{display:flex;align-items:center;gap:10px;min-width:0}
.lc-curriculum .cc-module-badge{min-width:44px;color:var(--cc-accent);font-size:clamp(1.55rem,2.4vw,2rem);font-weight:800;line-height:1;letter-spacing:-.045em;font-variant-numeric:tabular-nums}
.lc-curriculum .cc-module-summary{display:block;color:var(--cc-muted);font-size:12px;font-weight:500;margin-top:3px}
.lc-curriculum .cc-index{width:68px;color:var(--cc-muted);font-size:12px;font-variant-numeric:tabular-nums}
.lc-curriculum .cc-lesson-name{font-weight:700;color:var(--cc-text);text-decoration:none}
.lc-curriculum .cc-lesson-name:hover,.lc-curriculum .cc-lesson-name:focus-visible{color:var(--cc-accent);text-decoration:underline}
.lc-curriculum .cc-duration{white-space:nowrap;color:var(--cc-muted);font-variant-numeric:tabular-nums}
.lc-curriculum .cc-status{display:inline-flex;align-items:center;gap:6px;border-radius:99px;background:var(--cc-soft);color:var(--cc-muted);padding:5px 10px;font-size:11px;font-weight:750;white-space:nowrap}
.lc-curriculum .cc-status-complete{background:rgba(16,185,129,.12);color:var(--cc-success)}
.lc-curriculum .cc-actions{display:flex;align-items:center;justify-content:flex-end;flex-wrap:wrap;gap:7px}
.lc-curriculum .cc-actions .cc-button{padding:7px 11px;font-size:12px;white-space:nowrap}
.lc-curriculum .cc-action-text{white-space:nowrap;border:0;background:transparent;color:var(--cc-muted);padding:6px;font-size:11px;font-weight:700;cursor:pointer}
.lc-curriculum .cc-action-text:hover{text-decoration:underline;color:var(--cc-accent)}
.lc-curriculum .cc-foot{padding:12px 20px;border-top:1px solid var(--cc-border);display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center}
.lc-curriculum .cc-practice{margin-top:17px;padding:18px 20px}
.lc-curriculum .cc-practice h2{font-weight:800;font-size:16px;margin-bottom:4px}
.lc-curriculum .cc-practice-links{display:flex;flex-wrap:wrap;gap:9px;margin-top:13px}
.lc-curriculum .cc-empty{padding:30px 20px;text-align:center;color:var(--cc-muted)}
@media(max-width:640px){
  .lc-curriculum .cc-overview{grid-template-columns:1fr}
}
@media(max-width:800px){
  .lc-curriculum .cc-table thead{display:none}
  .lc-curriculum .cc-table,.lc-curriculum .cc-table tbody,.lc-curriculum .cc-table tr,.lc-curriculum .cc-table td{display:block;width:100%}
  .lc-curriculum .cc-table .cc-module th{display:block;width:100%}
  .lc-curriculum .cc-table .cc-lesson{display:grid;grid-template-columns:1fr auto;align-items:center;padding:12px 15px;gap:8px 12px;border-top:1px solid var(--cc-border)}
  .lc-curriculum .cc-table .cc-lesson td{border:0;padding:0}
  .lc-curriculum .cc-table .cc-lesson .cc-index{display:none}
  .lc-curriculum .cc-table .cc-lesson .cc-title-cell{grid-column:1/-1}
  .lc-curriculum .cc-table .cc-lesson .cc-status-cell{grid-column:2;grid-row:2}
  .lc-curriculum .cc-table .cc-lesson .cc-duration-cell{grid-column:1;grid-row:2}
  .lc-curriculum .cc-table .cc-lesson .cc-action-cell{grid-column:1/-1;grid-row:3}
  .lc-curriculum .cc-actions{justify-content:flex-start}
  .lc-curriculum .cc-table .cc-module th{padding:12px 15px}
  .lc-curriculum .cc-table-head{padding:16px}
  .lc-curriculum .cc-search{width:100%}
  .lc-curriculum .cc-search input{width:auto;flex:1;min-width:0}
}
</style>
@endpush
@section('content')
@php
    $totalLessons = $course->modules->sum(fn ($module) => $module->lessons->count());
    $finished = count($completedLessonIds);
    $percentage = $totalLessons > 0 ? round($finished * 100 / $totalLessons) : 0;
    $courseMinutes = $course->modules->sum(fn ($module) => $module->lessons->sum('duration_minutes'));
    $search = mb_strtolower(trim((string) request()->query('q', '')));
    $visibleCount = 0;
    foreach ($course->modules as $module) {
        $matchedModule = str_contains(mb_strtolower($module->title.' '.($module->summary ?? '')), $search);
        $visibleCount += $module->lessons->filter(fn ($lesson) => $search === '' || $matchedModule || str_contains(mb_strtolower($lesson->title), $search))->count();
    }
@endphp
<div class="lc-curriculum">
    <div class="cc-toolbar">
        <div class="cc-links">
            <a class="cc-button" href="{{ route('account.enrollments.index') }}"><x-workspace-icon name="chevron-right" class="h-4 w-4 rotate-180" /> My Enrollments</a>
            <a class="cc-button" href="{{ route('account.enrollments.resources', [$enrollment] + request()->only('lang')) }}"><x-workspace-icon name="paperclip" class="h-4 w-4" /> {{ __('focused_learning.course_resources') }}</a>
            <a class="cc-button" href="{{ route('account.notes.index', ['enrollment' => $enrollment->id]) }}"><x-workspace-icon name="notebook-text" class="h-4 w-4" /> My Lesson Notes</a>
        </div>
        <div class="cc-links">
            <span class="cc-status cc-status-complete">Enrollment approved</span>
            @if($resumeLesson && !$preview)
                <a class="cc-button cc-button-primary" href="{{ route('account.enrollments.show', [$enrollment] + request()->only('lang')) }}">
                    <x-workspace-icon name="play" class="h-4 w-4" />
                    {{ __('focused_learning.'.($savedLesson ? 'resume_learning' : ($finished ? 'continue_learning' : 'start_learning'))) }}
                </a>
            @endif
        </div>
    </div>

    <div class="cc-overview">
        <section class="cc-panel cc-overview-card cc-learning-progress" aria-labelledby="course-progress-title">
            <h2 id="course-progress-title" class="cc-card-heading">
                <span class="cc-card-icon"><x-workspace-icon name="target" class="h-4 w-4" /></span>
                {{ __('focused_learning.learning_progress') }}
            </h2>
            <div>
                <p class="cc-progress-value">{{ $percentage }}%</p>
                <p class="cc-summary-title">{{ __('focused_learning.lessons_completed', ['completed' => $finished, 'total' => $totalLessons]) }}</p>
            </div>
            <progress value="{{ $finished }}" max="{{ max(1, $totalLessons) }}" aria-label="{{ __('focused_learning.course_completion') }}"></progress>
            @if($savedLesson && !$preview)
                <div class="cc-saved-lesson">
                    <p class="cc-muted">{{ __('focused_learning.last_opened') }}</p>
                    <p>{{ $savedLesson->title }}</p>
                </div>
            @endif
            <div class="cc-card-footer">
                <p class="cc-muted">{{ __('focused_learning.self_reported_progress') }}</p>
                <a href="{{ route('account.progress.index') }}" class="cc-button">{{ __('focused_learning.view_progress') }} <x-workspace-icon name="chevron-right" class="h-4 w-4" /></a>
            </div>
        </section>

        <section class="cc-panel cc-overview-card" aria-labelledby="about-course-title">
            <h2 id="about-course-title" class="cc-card-heading">
                <span class="cc-card-icon"><x-workspace-icon name="info" class="h-4 w-4" /></span>
                {{ __('focused_learning.about_course') }}
            </h2>
            @if(filled($course->description_html) || filled($course->description))
                <div class="cc-about-body" tabindex="0" role="region" aria-labelledby="about-course-title">
                    <x-course-description :course="$course" />
                </div>
            @else
                <p class="cc-about-body">{{ $course->summary ?: __('focused_learning.no_description') }}</p>
            @endif
            <div class="cc-about-facts cc-muted">
                <span>{{ trans_choice('focused_learning.course_modules', $course->modules->count()) }}</span>
                <span>{{ __('focused_learning.course_minutes', ['count' => $courseMinutes]) }}</span>
            </div>
        </section>
    </div>

    <section class="cc-panel" aria-labelledby="course-curriculum-title">
        <div class="cc-table-head">
            <div>
                <h2 id="course-curriculum-title">Course curriculum</h2>
                <p class="cc-muted">{{ $course->modules->count() }} modules · {{ $totalLessons }} lessons · Open a lesson for the video, reading, quiz and resources.</p>
            </div>
            <form method="GET" action="{{ route('account.enrollments.show', $enrollment) }}" class="cc-search" role="search">
                <input type="hidden" name="view" value="curriculum">
                @if(request()->filled('lang'))<input type="hidden" name="lang" value="{{ request('lang') }}">@endif
                <label class="sr-only" for="curriculum-search">Search modules or lessons</label>
                <input id="curriculum-search" name="q" type="search" value="{{ request('q', '') }}" placeholder="Search modules or lessons…" maxlength="120">
                <button class="cc-button cc-button-primary" type="submit"><x-workspace-icon name="search" class="h-4 w-4" /> Search</button>
                @if($search !== '')<a class="cc-button" href="{{ route('account.enrollments.show', [$enrollment, 'view' => 'curriculum', 'lang' => request('lang', app()->getLocale())]) }}">Clear</a>@endif
            </form>
        </div>
        @if($visibleCount)
            <table class="cc-table">
                <thead>
                    <tr><th scope="col">No.</th><th scope="col">Lesson</th><th scope="col">Duration</th><th scope="col">Progress</th><th scope="col" style="text-align:right">Actions</th></tr>
                </thead>
                <tbody>
                    @foreach($course->modules as $module)
                        @php
                            $moduleMatches = $search !== '' && str_contains(mb_strtolower($module->title.' '.($module->summary ?? '')), $search);
                            $listedLessons = $module->lessons->filter(fn ($item) => $search === '' || $moduleMatches || str_contains(mb_strtolower($item->title), $search));
                            $moduleMinutes = $module->lessons->sum('duration_minutes');
                        @endphp
                        @continue($listedLessons->isEmpty())
                        <tr class="cc-module">
                            <th colspan="5" scope="rowgroup">
                                <div class="cc-module-heading">
                                    <div class="cc-module-name">
                                        <span class="cc-module-badge">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                        <span>
                                            Module {{ $loop->iteration }}: {{ $module->title }}
                                            @if($module->summary)<small class="cc-module-summary">{{ $module->summary }}</small>@endif
                                        </span>
                                    </div>
                                    <span class="cc-muted">{{ $module->lessons->count() }} lessons · {{ $moduleMinutes }} min</span>
                                </div>
                            </th>
                        </tr>
                        @foreach($listedLessons as $lesson)
                            @php $done = in_array($lesson->id, $completedLessonIds, true); @endphp
                            <tr class="cc-lesson">
                                <td class="cc-index">{{ $loop->parent->iteration }}.{{ $lesson->position }}</td>
                                <td class="cc-title-cell">
                                    <a class="cc-lesson-name" href="{{ route('account.lessons.watch', [$enrollment, $lesson]) }}">{{ $lesson->title }}</a>
                                </td>
                                <td class="cc-duration cc-duration-cell">{{ $lesson->duration_minutes ?: '—' }}{{ $lesson->duration_minutes ? ' min' : '' }}</td>
                                <td class="cc-status-cell">
                                    @if(filled($lesson->quiz_questions) && !$preview)
                                        <a class="cc-button" href="{{ route('account.lessons.watch', [$enrollment, $lesson, 'viewer' => 'quiz'] + request()->only('lang')) }}">
                                            {{ __(in_array($lesson->id, $quizCompletedIds, true) ? 'focused_learning.quiz_completed' : 'focused_learning.quiz') }}
                                        </a>
                                    @endif

                                    <span class="cc-status {{ $done ? 'cc-status-complete' : '' }}">
                                        <x-workspace-icon :name="$done ? 'check' : 'book-open'" class="h-3.5 w-3.5" />
                                        {{ $done ? 'Completed' : 'Not completed' }}
                                    </span>
                                </td>
                                <td class="cc-action-cell">
                                    <div class="cc-actions">
                                        <a class="cc-button" href="{{ route('account.lessons.watch', [$enrollment, $lesson]) }}"><x-workspace-icon name="play" class="h-3.5 w-3.5" /> Open lesson</a>
                                        @unless($preview)
                                            <form method="POST" action="{{ route('account.progress.update', [$enrollment, $lesson]) }}">
                                                @csrf
                                                <input type="hidden" name="complete" value="{{ $done ? '0' : '1' }}">
                                                <button class="cc-action-text" type="submit">{{ $done ? 'Mark for review' : 'Mark complete' }}</button>
                                            </form>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="cc-empty">{{ $search !== '' ? 'No modules or lessons match your search.' : 'No lessons have been added to this course.' }}</div>
        @endif
        <div class="cc-foot">
            <span class="cc-muted">Showing {{ $visibleCount }} of {{ $totalLessons }} lessons</span>
            @if($nextLesson && !$preview)<a class="cc-button" href="{{ route('account.lessons.watch', [$enrollment, $nextLesson]) }}">Go to next lesson <x-workspace-icon name="chevron-right" class="h-4 w-4" /></a>@endif
        </div>
    </section>

    @if($course->quizzes->isNotEmpty())
        <section class="cc-panel cc-practice" aria-labelledby="practice-quizzes-title">
            <h2 id="practice-quizzes-title">Practice quizzes</h2>
            <p class="cc-muted">Optional learning checks; these are not official examinations.</p>
            <div class="cc-practice-links">
                @foreach($course->quizzes as $quiz)
                    <a href="{{ route('account.practice.show', [$enrollment, $quiz]) }}" class="cc-button"><x-workspace-icon name="list-check" class="h-4 w-4" />{{ $quiz->title }}</a>
                @endforeach
            </div>
        </section>
    @endif
</div>
@include('workspace.student.course-examination')
@endsection
