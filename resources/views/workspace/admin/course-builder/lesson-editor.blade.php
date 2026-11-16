<section class="builder-editor" aria-labelledby="lesson-editor-heading">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h4 id="lesson-editor-heading" class="font-extrabold">{{ $editable ? ($editingLesson ? 'Edit lesson' : 'Add lesson') : $editingLesson->title }}</h4>
        <a class="builder-back" href="{{ $curriculumUrl($module) }}">{{ $editable ? 'Close editor' : 'Close lesson' }}</a>
    </div>
    @if($editable)
        <form id="course-curriculum-form" data-builder-edit method="POST" action="{{ $editingLesson ? route('admin.course-builder.lessons.update', [$course, $module, $editingLesson]) : route('admin.course-builder.lessons.store', [$course, $module]) }}" class="space-y-4">
            @csrf @if($editingLesson)@method('PATCH')@endif
            <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_6rem_8rem]">
                <label class="builder-field">Lesson title<input name="title" required maxlength="180" value="{{ old('title', $editingLesson?->title) }}"></label>
                <label class="builder-field">Order<input name="position" type="number" required min="1" max="999" value="{{ old('position', $editingLesson?->position ?? ((int) $module->lessons->max('position') + 1)) }}"></label>
                <label class="builder-field">Minutes<input name="duration_minutes" type="number" required min="1" max="600" value="{{ old('duration_minutes', $editingLesson?->duration_minutes ?? 15) }}"></label>
            </div>
            <div class="min-w-0">
                <label id="lesson-content-label" for="lesson-content-plain" class="mb-2 block text-sm font-bold">Lesson content</label>
                <x-rich-editor id="lesson-content" context="lesson" name="content" html-name="content_html"
                    labelled-by="lesson-content-label" :max-length="50000"
                    :value="old('content', $editingLesson?->content)" :html="old('content_html', $editingLesson?->content_html)" />
            </div>
            <label class="flex items-center gap-2 text-sm font-semibold"><input type="hidden" name="is_preview" value="0"><input type="checkbox" name="is_preview" value="1" @checked(old('is_preview', $editingLesson?->is_preview ?? false))> Public preview after approval</label>
        </form>
    @else
        <x-lesson-content :lesson="$editingLesson" class="text-sm leading-7" />
    @endif
</section>
