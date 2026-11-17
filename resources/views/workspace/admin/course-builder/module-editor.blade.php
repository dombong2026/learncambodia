<section class="builder-editor" aria-labelledby="module-editor-heading">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h4 id="module-editor-heading" class="font-extrabold">{{ $editingModule ? 'Edit module' : 'Add module' }}</h4>
        <a class="builder-back" href="{{ $curriculumUrl($editingModule) }}">Cancel</a>
    </div>
    <form id="course-curriculum-form" data-builder-edit data-order-editor="module" data-order-item="{{ $editingModule?->id }}" method="POST" action="{{ $editingModule ? route('admin.course-builder.modules.update', [$course, $editingModule]) : route('admin.course-builder.modules.store', $course) }}" class="space-y-4">
        @csrf @if($editingModule)@method('PATCH')@endif
        <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_7rem]">
            <label class="builder-field">Module title<input name="title" required maxlength="180" value="{{ old('title', $editingModule?->title) }}"></label>
            <label class="builder-field">Order<input name="position" type="number" required min="1" max="999" value="{{ old('position', $editingModule?->position ?? ((int) $course->modules->max('position') + 1)) }}"></label>
        </div>
        <label class="builder-field">Summary (optional)<textarea name="summary" rows="3" maxlength="700">{{ old('summary', $editingModule?->summary) }}</textarea></label>
    </form>
</section>
