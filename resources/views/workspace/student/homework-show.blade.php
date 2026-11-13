@extends('layouts.workspace')
@section('title', $assignment->title)
@section('heading', $assignment->title)
@section('description', 'Read your approved homework instructions, submit a text response and view instructor feedback.')
@section('content')
<a href="{{ route('account.homework.index') }}" class="inline-flex rounded-full border border-slate-300 px-5 py-2.5 text-sm font-bold dark:border-slate-700">← My Assignments</a>
<div class="mt-6 max-w-5xl space-y-5">
    <section class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <p class="text-xs font-bold uppercase tracking-widest text-blue-700 dark:text-blue-400">{{ $assignment->course?->title }}</p>
        <h2 class="mt-3 text-xl font-extrabold">Instructions</h2>
        <p class="mt-4 whitespace-pre-line break-words text-sm leading-8 text-slate-600 dark:text-slate-300">{{ $assignment->instructions }}</p>
        @if($assignment->rubric)<h3 class="mt-6 text-sm font-bold">Scoring criteria</h3><p class="mt-3 whitespace-pre-line text-sm leading-7 text-slate-600 dark:text-slate-400">{{ $assignment->rubric }}</p>@endif
        <p class="mt-5 text-xs font-semibold {{ $assignment->due_at?->isPast() ? 'text-amber-700 dark:text-amber-400' : 'text-slate-500' }}">{{ $assignment->due_at ? 'Deadline: '.$assignment->due_at->format('Y-m-d H:i') : 'No fixed deadline' }}</p>
    </section>
    @if($submission)
        <div class="rounded-2xl border border-blue-200 bg-blue-50 p-5 text-sm leading-7 text-blue-900 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200">
            <p class="font-extrabold">Your submission: {{ ucfirst($submission->status) }} · Version {{ $submission->version }} of 3</p>
            @if($submission->feedback)<p class="mt-2">Instructor feedback: {{ $submission->feedback }}</p>@endif
            @if($submission->status==='graded')<p class="mt-2 font-bold">Learning grade: {{ $submission->score }}/100</p>@endif
            <a href="{{ route('account.homework.result',$submission) }}" class="mt-3 inline-block font-bold underline">View submitted work and history</a>
        </div>
    @endif
    @php $canSubmit = (!$submission || $submission->status==='returned') && (!$submission || $submission->version<3) && (!$assignment->due_at || $assignment->due_at->isFuture()); @endphp
    @if($canSubmit)
    <form method="POST" action="{{ route('account.homework.submit',$assignment) }}" class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">@csrf
        <h2 class="text-lg font-extrabold">{{ $submission ? 'Resubmit revised answer' : 'Submit your answer' }}</h2>
        <p class="mt-2 text-xs leading-6 text-slate-500">Write at least ten characters. Your submitted text will be stored in a private revision history, visible to authorized instructors and yourself.</p>
        <label for="student-answer" class="mt-5 block text-sm font-bold">Your assignment response</label>
        <textarea id="student-answer" name="body" required minlength="10" maxlength="16000" rows="10" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm leading-7 dark:border-slate-700 dark:bg-slate-950">{{ old('body',$submission && $submission->status==='returned' ? $submission->body : '') }}</textarea>
        @error('body')<p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
        <div class="mt-5 flex justify-end"><button type="submit" class="rounded-full bg-blue-700 px-6 py-3 text-sm font-bold text-white">{{ $submission ? 'Resubmit revised work' : 'Submit for instructor review' }}</button></div>
    </form>
    @else
        <p class="rounded-2xl border border-slate-200 bg-white p-6 text-sm leading-7 text-slate-500 dark:border-slate-800 dark:bg-slate-900">No new submission is possible while work awaits feedback, after grading, when the deadline has passed or after three versions.</p>
    @endif
</div>
@endsection
