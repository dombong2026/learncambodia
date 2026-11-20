@extends('layouts.workspace')
@section('title', $course->exists ? 'Course Builder · '.$course->title : 'Create New Course')
@section('workspace_compact', '1')
@section('content')
@php
    $editable = !$course->exists || $course->isMutable();
    $steps = [
        'details' => ['Course Details', 'file', 'Set the course information learners will see in the catalog.'],
        'media' => ['Featured Media', 'media-library', 'Choose the cover or video displayed on the course page.'],
        'curriculum' => ['Curriculum', 'list-tree', 'Organize modules and edit one lesson at a time.'],
        'quizzes' => ['Practice Quizzes', 'help-circle', 'Add optional practice quizzes or continue to review.'],
        'review' => ['Review & Submit', 'shield', 'Check the saved course before sending it for approval.'],
    ];
    $keys = array_keys($steps);
    $position = array_search($step, $keys, true);
    $stepUrl = fn ($key, $params = []) => route('admin.course-builder.edit', ['course' => $course, 'step' => $key, ...$params]);
    $saveForm = $editable ? match ($step) {
        'details' => 'course-details-form',
        'media' => 'course-media-form',
        'curriculum' => ($editor === 'new-module' || ($module && in_array($editor, ['module', 'new-lesson'], true)) || $lesson) ? 'course-curriculum-form' : null,
        default => null,
    } : null;
@endphp
<div class="course-builder" data-course-builder>
    <header class="builder-header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('admin.courses.index') }}" class="builder-back">← Course Manager</a>
            @if($course->exists)
                <div class="flex flex-wrap items-center gap-2">
                    <span class="builder-status">{{ ucwords(str_replace('_', ' ', $course->status)) }} · Edition {{ $course->revision_number }}</span>
                    <a class="builder-button" href="{{ route('admin.course-builder.resources', $course) }}">Course Resources</a>
                    <a class="builder-button" href="{{ route('admin.course-builder.history.index', $course) }}">History &amp; readiness</a>
                    @if($course->status === 'published' && $course->superseded_at === null)
                        <form method="POST" action="{{ route('admin.course-builder.revise', $course) }}">@csrf
                            <button class="builder-button" type="submit">Create next edition</button>
                        </form>
                    @endif
                </div>
            @endif
        </div>
        <h1 class="mt-3 break-words text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $course->exists ? $course->title : 'Create New Course' }}</h1>
    </header>

    <nav class="builder-steps" aria-label="Course Builder steps">
        <ol class="builder-steps-desktop">
            @foreach($steps as $key => [$label, $icon])
                <li>
                    @if($course->exists)
                        <a href="{{ $stepUrl($key) }}" class="builder-step" @if($key === $step) aria-current="step" @endif>
                            <span class="builder-step-number">{{ $loop->iteration }}</span><span>{{ $label }}</span>
                        </a>
                    @else
                        <span class="builder-step" @if($key === $step) aria-current="step" @else aria-disabled="true" @endif>
                            <span class="builder-step-number">{{ $loop->iteration }}</span><span>{{ $label }}</span>
                        </span>
                    @endif
                </li>
            @endforeach
        </ol>
        <details class="builder-steps-mobile">
            <summary class="builder-row font-bold">Step {{ $position + 1 }} of 5 · {{ $steps[$step][0] }} <x-workspace-icon name="chevron-down" class="ml-auto h-4 w-4" /></summary>
            <ol class="space-y-1 p-2">
                @foreach($steps as $key => [$label])
                    <li>
                        @if($course->exists)
                            <a href="{{ $stepUrl($key) }}" class="builder-step" @if($key === $step) aria-current="step" @endif>
                                <span class="builder-step-number">{{ $loop->iteration }}</span>{{ $label }}
                            </a>
                        @else
                            <span class="builder-step" @if($key === $step) aria-current="step" @else aria-disabled="true" @endif>
                                <span class="builder-step-number">{{ $loop->iteration }}</span>{{ $label }}
                            </span>
                        @endif
                    </li>
                @endforeach
            </ol>
        </details>
    </nav>

    <section class="builder-panel" aria-labelledby="builder-step-heading">
        <header class="builder-panel-heading">
            <span class="builder-panel-icon"><x-workspace-icon :name="$steps[$step][1]" class="h-5 w-5" /></span>
            <div>
                <h2 id="builder-step-heading" class="text-xl font-extrabold">{{ $steps[$step][0] }}</h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $steps[$step][2] }}</p>
            </div>
        </header>
        <div class="builder-panel-content">
            @unless($editable)
                <p class="builder-callout"><x-workspace-icon name="lock" class="h-4 w-4 shrink-0" /> This edition is read-only.</p>
            @endunless
            @if($errors->any())
                <div role="alert" class="builder-errors">
                    <p class="font-bold">Please check the highlighted information.</p>
                    <ul class="mt-1 list-disc space-y-1 pl-5">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
                </div>
            @endif
            @include('workspace.admin.course-builder.'.$step)
        </div>
        <footer class="builder-footer">
            <div class="flex min-w-0 flex-wrap items-center gap-3">
                @if($position > 0)
                    <a class="builder-button" href="{{ $stepUrl($keys[$position - 1]) }}">← Back</a>
                @endif
                <small data-builder-unsaved hidden role="status" class="font-semibold text-amber-800 dark:text-amber-200">Unsaved changes</small>
            </div>
            <div class="ml-auto flex flex-wrap justify-end gap-2">
                @if($saveForm)
                    <button class="builder-button" type="submit" form="{{ $saveForm }}">{{ $step === 'curriculum' ? 'Save changes' : 'Save draft' }}</button>
                    <button class="builder-button builder-primary" type="submit" form="{{ $saveForm }}" name="continue" value="1">
                        {{ !$course->exists ? 'Create draft & continue' : 'Save & continue' }} <span aria-hidden="true">→</span>
                    </button>
                @elseif($step === 'review' && $editable)
                    <button class="builder-button builder-primary" type="submit" form="course-submit-form" @disabled(in_array(false, $reviewChecklist, true))>Submit for approval</button>
                @elseif($position < 4)
                    <a class="builder-button builder-primary" href="{{ $stepUrl($keys[$position + 1]) }}">Continue <span aria-hidden="true">→</span></a>
                @endif
            </div>
        </footer>
    </section>
</div>
@endsection
