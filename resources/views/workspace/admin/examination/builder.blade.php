@extends('layouts.workspace')
@section('heading', 'Exam Builder')
@section('content')
    @if($errors->any())
        <ul role="alert" class="mb-4 list-disc space-y-1 pl-5 text-red-600 dark:text-red-300">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    @endif
    <form data-exam-builder method="post" action="{{ $exam->exists ? route('admin.exams.update', $exam) : route('admin.exams.store') }}" class="space-y-5">
        @csrf @if($exam->exists)@method('PATCH')@endif
        <fieldset class="workspace-panel grid gap-4 p-5 sm:grid-cols-2">
            <label class="builder-field sm:col-span-2">Exam title<input name="title" required maxlength="160" value="{{ old('title', $exam->title) }}"></label>
            <label class="builder-field">Course
                <select name="course_id" required class="rounded-xl border px-3 py-2">
                    <option value="">Choose a course</option>
                    @foreach($courses as $course)
                        <option value="{{ $course->id }}" @selected((int) old('course_id', $exam->course_id) === $course->id)>{{ $course->title }}</option>
                    @endforeach
                </select>
            </label>
            <label class="builder-field">Exam type
                <select name="exam_type_id" required class="rounded-xl border px-3 py-2">
                    <option value="">Choose a type</option>
                    @foreach($types as $type)
                        <option value="{{ $type->id }}" @selected((int) old('exam_type_id', $exam->exam_type_id) === $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
                <a class="text-blue-600 dark:text-blue-300" href="{{ route('admin.exam-types.index') }}">Manage types</a>
            </label>
            <div class="grid gap-4 sm:col-span-2 sm:grid-cols-3">
                @foreach(['time_limit'=>['Time limit (minutes)',45,240], 'passing_percent'=>['Pass score (%)',70,100], 'attempt_limit'=>['Attempts allowed',2,10]] as $field=>[$label,$default,$maximum])
                    <label class="builder-field">{{ $label }}
                        <input name="{{ $field }}" type="number" min="1" max="{{ $maximum }}" value="{{ old($field, $exam->$field ?? $default) }}" required>
                    </label>
                @endforeach
            </div>
            @foreach(['shuffle'=>'Shuffle questions', 'gamified'=>'Show milestones and score feedback', 'published'=>'Publish for approved learners'] as $key => $label)
                <label class="flex items-center gap-2 text-sm font-semibold">
                    <input type="hidden" name="{{ $key }}" value="0">
                    <input type="checkbox" name="{{ $key }}" value="1" @checked(old($key,$exam->$key))>{{ $label }}
                </label>
            @endforeach
        </fieldset>

        <section class="workspace-panel space-y-3 p-5">
            <h2 id="exam-instructions-label" class="font-extrabold">Instructions, activities & resources</h2>
            <p class="text-sm text-slate-600 dark:text-slate-300">Add task instructions and reading passages. Upload video, audio, images, PDF, Word, or Excel files.</p>
            <x-rich-editor id="exam-instructions" context="exam" name="instructions" html-name="instructions_html"
                labelled-by="exam-instructions-label" :required="false" :max-length="15000"
                :value="old('instructions')" :html="old('instructions_html', $exam->instructions_html)" />
        </section>

        <section class="workspace-panel space-y-4 p-5">
            <h2 class="font-extrabold">Scored questions</h2>
            <div data-exam-questions class="space-y-4">
                @foreach(old('questions', $exam->questions ?? [[]]) as $index => $question)
                    @include('workspace.admin.examination.question', ['index' => $index, 'question' => $question])
                @endforeach
            </div>
            <template data-exam-template>@include('workspace.admin.examination.question', ['index' => 0, 'question' => []])</template>
            <button type="button" data-exam-add class="rounded-xl border border-slate-400 px-4 py-2 text-sm font-semibold">Add question</button>
        </section>
        <button class="rounded-xl bg-blue-700 px-5 py-3 text-sm font-bold text-white">Save exam</button>
    </form>
    @if($attempts)
        <section class="workspace-panel mt-5 space-y-3 p-5">
            <h2 class="font-extrabold">Submitted attempts</h2>
            @forelse($attempts as $result)
                <p class="text-sm">{{ $result->enrollment->student->name }} · {{ $result->score }}% · {{ $result->passed ? 'Passed' : 'Below pass score' }} · {{ $result->submitted_at->toDateString() }}</p>
            @empty
                <p class="text-sm">No submitted attempts.</p>
            @endforelse
            {{ $attempts->links() }}
        </section>
    @endif
@endsection
