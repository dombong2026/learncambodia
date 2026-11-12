@extends('layouts.workspace')
@section('title', $assignment->title)
@section('heading', $assignment->title)
@section('description', 'Instructor-authored text assignment. No student can see changes until a separate school administrator approves the publication.')
@section('content')
<a href="{{ route('instructor.homework.index') }}" class="inline-flex rounded-full border border-slate-300 px-5 py-2.5 text-sm font-bold dark:border-slate-700">← Homework Builder</a>
<div class="mt-6 max-w-5xl space-y-5">
    <section class="rounded-3xl border border-slate-200 bg-white p-7 dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-xl font-extrabold">Assignment content</h2><span class="rounded-full bg-blue-50 px-4 py-2 text-xs font-bold text-blue-700 dark:bg-blue-950 dark:text-blue-300">{{ ucwords(str_replace('_',' ', $assignment->status)) }}</span></div>
        @if($assignment->review_note)<p class="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:bg-amber-950 dark:text-amber-300">Reviewer: {{ $assignment->review_note }}</p>@endif
        <form method="POST" action="{{ route('instructor.homework.update',$assignment) }}" class="mt-6 space-y-5">@csrf @method('PATCH')
            <fieldset @disabled(!$assignment->isEditable()) class="space-y-5">
                <div><label for="homework-title" class="mb-2 block text-sm font-bold">Title</label><input id="homework-title" name="title" maxlength="180" required value="{{ old('title',$assignment->title) }}" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">@error('title')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div><label for="homework-instructions" class="mb-2 block text-sm font-bold">Instructions</label><textarea id="homework-instructions" name="instructions" rows="8" maxlength="12000" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm leading-7 dark:border-slate-700 dark:bg-slate-950">{{ old('instructions',$assignment->instructions) }}</textarea>@error('instructions')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div><label for="homework-rubric" class="mb-2 block text-sm font-bold">Grading rubric (optional)</label><textarea id="homework-rubric" name="rubric" rows="4" maxlength="3500" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm leading-7 dark:border-slate-700 dark:bg-slate-950">{{ old('rubric',$assignment->rubric) }}</textarea></div>
                <div><label for="homework-due" class="mb-2 block text-sm font-bold">Due date and time</label><input id="homework-due" name="due_at" type="datetime-local" value="{{ old('due_at', $assignment->due_at?->format('Y-m-d\TH:i')) }}" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">@error('due_at')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                @if($assignment->isEditable())<div class="flex justify-end"><button type="submit" class="rounded-full bg-blue-700 px-6 py-3 text-sm font-bold text-white">Save draft</button></div>@endif
            </fieldset>
        </form>
    </section>
    @if($assignment->isEditable())
    <form method="POST" action="{{ route('instructor.homework.submit',$assignment) }}" class="flex flex-wrap items-center justify-between gap-4 rounded-3xl border border-slate-200 bg-white p-7 dark:border-slate-800 dark:bg-slate-900">@csrf
        <p class="max-w-lg text-sm leading-7 text-slate-500">Submit the saved draft for academic review. You cannot edit during review or after publication.</p>
        <button type="submit" class="rounded-full bg-slate-900 px-6 py-3 text-sm font-bold text-white dark:bg-blue-700">Submit for academic approval</button>
    </form>
    @else
    <p class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm font-semibold text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300">This assignment is locked. Contact an administrator if a new reviewed version is needed.</p>
    @endif
</div>
@endsection
