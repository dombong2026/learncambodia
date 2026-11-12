@extends('layouts.workspace')
@section('title', 'Instructor Course')
@section('heading', $course->title)
@section('description', 'Manage your own homework drafts for this assigned course. Administrators review assignments independently before students can submit work.')
@section('content')
<a href="{{ route('instructor.courses.index') }}" class="inline-block rounded-full border border-slate-300 px-5 py-2.5 text-sm font-semibold dark:border-slate-700">← My Assigned Courses</a>
<div class="mt-6 rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <h2 class="text-xl font-extrabold">Create a course assignment</h2>
    <form method="POST" action="{{ route('instructor.homework.store',$course) }}" class="mt-5 space-y-4">@csrf
        <div><label for="new-title" class="mb-2 block text-sm font-bold">Assignment title</label><input id="new-title" name="title" maxlength="180" value="{{ old('title') }}" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">@error('title')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div><label for="new-instructions" class="mb-2 block text-sm font-bold">Learning instructions</label><textarea id="new-instructions" name="instructions" rows="5" maxlength="12000" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">{{ old('instructions') }}</textarea>@error('instructions')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div><label for="new-rubric" class="mb-2 block text-sm font-bold">Grading rubric (optional, out of 100)</label><textarea id="new-rubric" name="rubric" rows="3" maxlength="3500" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">{{ old('rubric') }}</textarea></div>
        <div><label for="new-due" class="mb-2 block text-sm font-bold">Due date and time (optional)</label><input id="new-due" type="datetime-local" name="due_at" value="{{ old('due_at') }}" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">@error('due_at')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div class="flex justify-end"><button type="submit" class="rounded-full bg-blue-700 px-6 py-3 text-sm font-bold text-white">Create draft assignment</button></div>
    </form>
</div>
<div class="mt-8">
    <h2 class="mb-5 text-xl font-extrabold">My assignments for this course</h2>
    <div class="space-y-3">
    @forelse($assignments as $assignment)
        <a href="{{ route('instructor.homework.edit',$assignment) }}" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-5 text-sm transition hover:border-blue-400 dark:border-slate-800 dark:bg-slate-900"><span><strong>{{ $assignment->title }}</strong><span class="ml-2 text-xs font-semibold text-slate-500">{{ ucwords(str_replace('_',' ', $assignment->status)) }}</span></span><span class="font-bold text-blue-700 dark:text-blue-400">Open →</span></a>
    @empty<p class="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-900">No assignments for this course yet.</p>@endforelse
    </div><div class="mt-6">{{ $assignments->links() }}</div>
</div>
@endsection
