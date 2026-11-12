@extends('layouts.workspace')
@section('title', $course->exists ? 'Course Builder' : 'Create Course')
@section('heading', $course->exists ? 'Course Builder: '.$course->title : 'Create New Course')
@section('description', 'Draft and rejected courses are editable; submitted and published curricula are locked. A different administrator must approve publication.')
@section('content')
@php $editable = !$course->exists || $course->isMutable(); @endphp
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <a href="{{ route('admin.courses.index') }}" class="rounded-full border border-slate-300 px-4 py-2.5 text-sm font-semibold dark:border-slate-700">← Course Manager</a>
    @if($course->exists)<span class="rounded-full bg-blue-50 px-4 py-2 text-sm font-bold text-blue-700 dark:bg-blue-950 dark:text-blue-300">{{ ucwords(str_replace('_',' ', $course->status)) }}</span>@endif
</div>
<form method="POST" action="{{ $course->exists ? route('admin.course-builder.update',$course) : route('admin.course-builder.store') }}" class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
    @csrf @if($course->exists)@method('PATCH')@endif
    <fieldset @disabled(!$editable) class="grid gap-5 sm:grid-cols-2">
        <div class="sm:col-span-2"><label for="course-title" class="mb-2 block text-sm font-bold">Course title</label><input id="course-title" name="title" maxlength="180" required value="{{ old('title',$course->title) }}" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">@error('title')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div><label for="course-slug" class="mb-2 block text-sm font-bold">URL slug (optional)</label><input id="course-slug" name="slug" maxlength="180" value="{{ old('slug',$course->slug) }}" placeholder="Auto-generated if blank" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">@error('slug')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div><label for="course-category" class="mb-2 block text-sm font-bold">Category</label><select id="course-category" name="category_id" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">Select a category</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((int)old('category_id',$course->category_id)===$category->id)>{{ $category->code }} · {{ $category->name }} {{ $category->is_published ? '' : '(unpublished)' }}</option>@endforeach</select>@error('category_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div><label for="course-level" class="mb-2 block text-sm font-bold">CEFR level</label><select id="course-level" name="cefr_level" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">All levels</option>@foreach($levels as $level)<option value="{{ $level->code }}" @selected(old('cefr_level',$course->cefr_level)===$level->code)>{{ $level->code }} · {{ $level->name }}</option>@endforeach</select>@error('cefr_level')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div><label for="course-mode" class="mb-2 block text-sm font-bold">Delivery mode</label><select id="course-mode" name="delivery_mode" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">@foreach(['online'=>'Online','onsite'=>'On-site','hybrid'=>'Hybrid'] as $key=>$name)<option value="{{ $key }}" @selected(old('delivery_mode',$course->delivery_mode ?? 'online')===$key)>{{ $name }}</option>@endforeach</select></div>
        <div class="sm:col-span-2"><label for="course-summary" class="mb-2 block text-sm font-bold">Summary</label><textarea id="course-summary" name="summary" maxlength="1000" rows="3" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm leading-7 dark:border-slate-700 dark:bg-slate-950">{{ old('summary',$course->summary) }}</textarea>@error('summary')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div class="sm:col-span-2"><label for="course-description" class="mb-2 block text-sm font-bold">Full course description</label><textarea id="course-description" name="description" maxlength="25000" rows="5" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm leading-7 dark:border-slate-700 dark:bg-slate-950">{{ old('description',$course->description) }}</textarea>@error('description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        @if($editable)<div class="sm:col-span-2 flex justify-end"><button type="submit" class="rounded-full bg-blue-700 px-6 py-3 text-sm font-bold text-white hover:bg-blue-800">{{ $course->exists ? 'Save course' : 'Create draft course' }}</button></div>@endif
    </fieldset>
</form>
@if($course->exists)
    @if($course->review_note)<div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200"><strong>Reviewer note:</strong> {{ $course->review_note }}</div>@endif
    <h2 class="mt-10 text-2xl font-extrabold">Curriculum modules & lessons</h2>
    <p class="mt-2 text-sm text-slate-500">Each module requires at least one lesson before course submission. Lesson content is plain text and does not run scripts or embedded media.</p>
    @foreach($course->modules as $module)
        <details class="mt-5 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900" open>
            <summary class="cursor-pointer text-lg font-extrabold">Module {{ $module->position }} · {{ $module->title }} <span class="text-sm font-normal text-slate-500">({{ $module->lessons->count() }} lessons)</span></summary>
            @if($editable)
            <form method="POST" action="{{ route('admin.course-builder.modules.update',[$course,$module]) }}" class="mt-5 grid gap-3 sm:grid-cols-[1fr_100px_auto]">@csrf @method('PATCH')
                <input name="title" value="{{ $module->title }}" maxlength="180" required aria-label="Module title" class="rounded-xl border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                <input name="position" type="number" min="1" max="999" value="{{ $module->position }}" required aria-label="Module order" class="rounded-xl border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                <input type="hidden" name="summary" value="{{ $module->summary }}">
                <button class="rounded-xl bg-blue-700 px-4 py-2 text-sm font-bold text-white">Save</button>
            </form>
            <form method="POST" action="{{ route('admin.course-builder.modules.destroy',[$course,$module]) }}" class="mt-2 flex justify-end">@csrf @method('DELETE')<button type="submit" class="text-xs font-semibold text-red-600">Delete module & lessons</button></form>
            @endif
            <div class="mt-5 space-y-4">
            @foreach($module->lessons as $lesson)
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 dark:border-slate-800 dark:bg-slate-950">
                    <h3 class="text-sm font-extrabold">{{ $lesson->position }} · {{ $lesson->title }} <span class="font-normal text-slate-500">({{ $lesson->duration_minutes }} min)</span></h3>
                    @if($editable)
                    <form method="POST" action="{{ route('admin.course-builder.lessons.update',[$course,$module,$lesson]) }}" class="mt-4 space-y-3">@csrf @method('PATCH')
                        <div class="grid gap-3 sm:grid-cols-[1fr_90px_110px]">
                            <input name="title" value="{{ $lesson->title }}" maxlength="180" required aria-label="Lesson title" class="rounded-xl border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                            <input name="position" type="number" value="{{ $lesson->position }}" min="1" max="999" required aria-label="Lesson order" class="rounded-xl border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                            <input name="duration_minutes" type="number" value="{{ $lesson->duration_minutes }}" min="1" max="600" required aria-label="Duration minutes" class="rounded-xl border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                        </div>
                        <textarea name="content" maxlength="50000" rows="4" required aria-label="Lesson content" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm leading-7 dark:border-slate-700 dark:bg-slate-900">{{ $lesson->content }}</textarea>
                        <label class="flex items-center gap-2 text-xs font-semibold"><input type="hidden" name="is_preview" value="0"><input type="checkbox" name="is_preview" value="1" @checked($lesson->is_preview)> Preview lesson</label>
                        <div class="flex justify-end"><button type="submit" class="rounded-full border border-blue-400 px-5 py-2 text-xs font-bold text-blue-700 dark:text-blue-400">Save lesson</button></div>
                    </form>
                    <form method="POST" action="{{ route('admin.course-builder.lessons.destroy',[$course,$module,$lesson]) }}" class="mt-2 flex justify-end">@csrf @method('DELETE')<button type="submit" class="text-xs font-semibold text-red-600">Delete lesson</button></form>
                    @else<p class="mt-2 whitespace-pre-line text-sm leading-7 text-slate-600 dark:text-slate-400">{{ $lesson->content }}</p>@endif
                </div>
            @endforeach
            </div>
            @if($editable)
            <form method="POST" action="{{ route('admin.course-builder.lessons.store',[$course,$module]) }}" class="mt-6 space-y-3 rounded-2xl border border-dashed border-blue-300 p-5 dark:border-blue-900">@csrf
                <h4 class="text-sm font-bold">Add lesson</h4>
                <div class="grid gap-3 sm:grid-cols-[1fr_90px_110px]">
                    <input name="title" placeholder="Lesson title" maxlength="180" required aria-label="New lesson title" class="rounded-xl border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                    <input name="position" type="number" min="1" max="999" value="{{ $module->lessons->count()+1 }}" required aria-label="New lesson order" class="rounded-xl border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                    <input name="duration_minutes" type="number" min="1" max="600" value="15" required aria-label="Duration minutes" class="rounded-xl border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                </div>
                <textarea name="content" rows="4" maxlength="50000" required placeholder="Plain-text learning content" aria-label="New lesson content" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"></textarea>
                <label class="flex items-center gap-2 text-xs font-semibold"><input type="hidden" name="is_preview" value="0"><input type="checkbox" name="is_preview" value="1"> Public preview after approval</label>
                <div class="flex justify-end"><button type="submit" class="rounded-full border border-blue-500 px-5 py-2 text-sm font-bold text-blue-700 dark:text-blue-400">Add lesson</button></div>
            </form>
            @endif
        </details>
    @endforeach

    <section class="mt-10 rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-xl font-extrabold">Practice quizzes</h2>
        <p class="mt-2 text-sm leading-7 text-slate-500">Practice questions are included in the course review. A quiz needs at least one complete four-option question before submission.</p>
        <div class="mt-5 space-y-3">
            @forelse($course->quizzes as $quiz)
            <a href="{{ route('admin.course-builder.quizzes.edit', [$course,$quiz]) }}" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 px-5 py-4 transition hover:border-blue-400 dark:border-slate-800">
                <span><strong>{{ $quiz->title }}</strong><span class="ml-2 text-xs text-slate-500">{{ $quiz->questions()->count() }} questions</span></span>
                <span class="text-sm font-bold text-blue-700 dark:text-blue-400">Open quiz →</span>
            </a>
            @empty
            <p class="text-sm text-slate-500">No practice quiz added to this course.</p>
            @endforelse
        </div>
        @if($editable)
        <form method="POST" action="{{ route('admin.course-builder.quizzes.store',$course) }}" class="mt-6 space-y-4 rounded-2xl border border-dashed border-blue-300 bg-blue-50 p-5 dark:border-blue-900 dark:bg-slate-950">@csrf
            <h3 class="text-base font-bold">Add a practice quiz</h3>
            <div class="grid gap-3 sm:grid-cols-2">
                <div><label class="mb-2 block text-xs font-bold" for="new-quiz-title">Title</label><input id="new-quiz-title" name="title" maxlength="180" required placeholder="Unit review quiz" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-900"></div>
                <div><label class="mb-2 block text-xs font-bold" for="new-quiz-pass">Passing score (%)</label><input id="new-quiz-pass" name="passing_percent" type="number" min="0" max="100" value="70" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-900"></div>
            </div>
            <div><label class="mb-2 block text-xs font-bold" for="new-quiz-instructions">Instructions (optional)</label><textarea id="new-quiz-instructions" name="instructions" maxlength="1000" rows="2" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-900"></textarea></div>
            <input type="hidden" name="position" value="{{ $course->quizzes->count()+1 }}">
            <div class="flex justify-end"><button type="submit" class="rounded-full bg-blue-700 px-6 py-3 text-sm font-bold text-white">Create practice quiz</button></div>
        </form>
        @endif
    </section>
    @if($editable)
        <form method="POST" action="{{ route('admin.course-builder.modules.store',$course) }}" class="mt-6 rounded-3xl border border-dashed border-blue-300 bg-blue-50 p-6 dark:border-blue-900 dark:bg-slate-900">@csrf
            <h3 class="text-lg font-extrabold">Add curriculum module</h3>
            <div class="mt-4 grid gap-3 sm:grid-cols-[1fr_100px]">
                <input name="title" placeholder="Module title" maxlength="180" required aria-label="Module title" class="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">
                <input name="position" type="number" min="1" max="999" value="{{ $course->modules->count()+1 }}" required aria-label="Module order" class="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">
            </div>
            <textarea name="summary" rows="2" maxlength="700" placeholder="Optional module summary" class="mt-3 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950"></textarea>
            <div class="mt-4 flex justify-end"><button type="submit" class="rounded-full bg-blue-700 px-6 py-3 text-sm font-bold text-white">Add module</button></div>
        </form>
        <form method="POST" action="{{ route('admin.course-builder.submit',$course) }}" class="mt-7 flex flex-wrap items-center justify-between gap-3 rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">@csrf
            <p class="max-w-lg text-sm leading-7 text-slate-500">At least one lesson per module, a full description and a published category are required for review.</p>
            <button type="submit" class="rounded-full bg-slate-900 px-6 py-3 text-sm font-bold text-white dark:bg-blue-700">Submit course for approval</button>
        </form>
    @else
        <p class="mt-7 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm font-semibold text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300">The curriculum is locked in its current state. Publication changes require reviewed versioning.</p>
    @endif
@endif
@endsection
