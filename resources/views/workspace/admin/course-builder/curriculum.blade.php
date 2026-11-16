@php
    $currentLesson = $lesson;
    $curriculumUrl = fn ($currentModule = null, $mode = null, $currentLessonId = null) => $stepUrl('curriculum', array_filter([
        'module' => $currentModule?->id, 'editor' => $mode, 'lesson' => $currentLessonId,
    ]));
@endphp
<div class="builder-curriculum">
    <aside class="builder-outline" aria-label="Curriculum outline">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h3 class="font-extrabold">Modules</h3>
            @if($editable)<a class="builder-button" href="{{ $curriculumUrl(null, 'new-module') }}">+ Add module</a>@endif
        </div>
        <div class="space-y-2">
            @forelse($course->modules as $item)
                <a class="builder-row builder-outline-row" href="{{ $curriculumUrl($item) }}" @if($module?->id === $item->id && $editor !== 'new-module') aria-current="true" @endif>
                    <span class="builder-step-number">{{ $item->position }}</span>
                    <span class="min-w-0 flex-1">
                        <strong class="block break-words text-sm">{{ $item->title }}</strong>
                        <span class="mt-1 block text-xs text-slate-600 dark:text-slate-300">{{ $item->lessons_count }} {{ Str::plural('lesson', $item->lessons_count) }} · {{ (int) $item->lessons_sum_duration_minutes }} min</span>
                    </span>
                </a>
            @empty
                <p class="text-sm text-slate-600 dark:text-slate-300">No modules yet.</p>
            @endforelse
        </div>
    </aside>
    <div class="min-w-0 space-y-5">
        @if($editable && $editor === 'new-module')
            @include('workspace.admin.course-builder.module-editor', ['editingModule' => null])
        @elseif($module)
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <h3 class="break-words text-lg font-extrabold">{{ $module->title }}</h3>
                    @if($module->summary)<p class="mt-1 break-words text-sm text-slate-600 dark:text-slate-300">{{ $module->summary }}</p>@endif
                </div>
                @if($editable)
                    @include('workspace.admin.course-builder.actions', ['itemType' => 'module', 'item' => $module, 'editUrl' => $curriculumUrl($module, 'module')])
                @endif
            </div>
            @if($editable && $editor === 'module')
                @include('workspace.admin.course-builder.module-editor', ['editingModule' => $module])
            @else
                <div class="space-y-2">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <h4 class="font-bold">Lessons</h4>
                        @if($editable)<a class="builder-button" href="{{ $curriculumUrl($module, 'new-lesson') }}">+ Add lesson</a>@endif
                    </div>
                    @forelse($module->lessons as $item)
                        <div class="builder-row">
                            <a class="flex min-w-0 flex-1 items-center gap-3 rounded-md text-sm font-semibold hover:underline" href="{{ $curriculumUrl($module, 'lesson', $item->id) }}" @if($currentLesson?->id === $item->id) aria-current="true" @endif>
                                <x-workspace-icon name="file" class="h-4 w-4 shrink-0 text-blue-700 dark:text-blue-300"/>
                                <span class="min-w-0 flex-1 break-words">{{ $item->title }}</span>
                                <span class="shrink-0 text-xs text-slate-600 dark:text-slate-300">{{ $item->duration_minutes }} min</span>
                            </a>
                            @if($editable)
                                @include('workspace.admin.course-builder.actions', ['itemType' => 'lesson', 'item' => $item, 'editUrl' => $curriculumUrl($module, 'lesson', $item->id)])
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-slate-600 dark:text-slate-300">This module has no lessons.</p>
                    @endforelse
                </div>
                @if($currentLesson || ($editable && $editor === 'new-lesson'))
                    @include('workspace.admin.course-builder.lesson-editor', ['editingLesson' => $currentLesson])
                @endif
            @endif
        @else
            <p class="rounded-xl border border-dashed border-slate-300 p-6 text-sm text-slate-600 dark:border-slate-700 dark:text-slate-300">{{ $editable ? 'Add the first module to start building the curriculum.' : 'This course has no curriculum modules.' }}</p>
        @endif
    </div>
</div>
