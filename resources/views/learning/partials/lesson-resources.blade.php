@php
    $courseResources = $courseResources ?? collect();
    $courseFiles = $courseResources->pluck('editor_media_id')->all();
    $lessonFiles = array_filter($materials['files'], fn ($file) => ! in_array($file['id'] ?? null, $courseFiles, true));
@endphp
@if($courseResources->isNotEmpty())
    <div class="mb-5">
        <h2>{{ __('focused_learning.course_resources') }}</h2>
        <x-course-resource-list :resources="$courseResources" :enrollment="$enrollment" />
        <a class="fl-link" href="{{ route('account.enrollments.resources', [$enrollment] + request()->only('lang')) }}">{{ __('focused_learning.all_course_resources') }}</a>
    </div>
@endif
<h2>{{ __("focused_learning.lesson_resources") }}</h2>
@if($lessonFiles || $materials['links'] || isset($materials['viewers']['quiz']) || $quizzes->isNotEmpty() || $assignments->isNotEmpty() || count($materials['viewers']) > 1)
    <ul class="fl-resources">
        @if(isset($materials['viewers']['quiz']))
            <li><a href="{{ $lessonUrl(['viewer' => 'quiz', 'tab' => 'resources']) }}#lesson-viewer"><x-workspace-icon name="list-check" /> Lesson quiz <span>Open</span></a></li>
        @endif
        @foreach($materials['viewers'] as $key => $material)
            @if(in_array($material['type'], ['exam', 'youtube'], true))
                <li><a href="{{ $lessonUrl(['viewer' => $key, 'tab' => 'resources']) }}#lesson-viewer"><x-workspace-icon :name="$types[$material['type']][0]" /> {{ $material['label'] }} <span>Open</span></a></li>
            @endif
        @endforeach
        @foreach($lessonFiles as $file)
            <li><a href="{{ $file['viewer'] ? $lessonUrl(['viewer' => $file['viewer'], 'tab' => 'resources']).'#lesson-viewer' : $file['url'] }}" @if(!$file['viewer']) target="_blank" rel="noopener" @endif>
                <x-workspace-icon name="file" /> {{ $file['label'] }} <span>{{ $file['viewer'] ? 'Preview' : 'Open file' }}</span>
            </a></li>
        @endforeach
        @foreach($materials['links'] as $url => $label)
            <li><a href="{{ $url }}" target="_blank" rel="noopener noreferrer"><x-workspace-icon name="link" /> {{ $label }} <span>Open link</span></a></li>
        @endforeach
        @foreach($quizzes as $quiz)
            <li><a href="{{ route('account.practice.show', [$enrollment, $quiz, 'from_lesson' => $lesson->id]) }}"><x-workspace-icon name="list-check" /> {{ $quiz->title }} <span>Practice</span></a></li>
        @endforeach
        @foreach($assignments as $assignment)
            <li><a href="{{ route('account.homework.show', $assignment) }}"><x-workspace-icon name="notebook-pen" /> {{ $assignment->title }} <span>Exercise</span></a></li>
        @endforeach
    </ul>
@else
    <p class="fl-muted">{{ __("focused_learning.no_resources") }}</p>
@endif
