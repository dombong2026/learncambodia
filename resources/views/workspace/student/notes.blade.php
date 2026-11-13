@extends('layouts.workspace')
@section('title', 'My Lesson Notes')
@section('heading', 'My Lesson Notes')
@section('description', 'Private notes from lessons in your approved courses. No notes are shared with instructors or other students.')
@section('content')
<form method="GET" action="{{ route('account.notes.index') }}" class="grid gap-3 rounded-3xl border border-slate-200 bg-white p-5 sm:grid-cols-[1fr_1fr_auto] dark:border-slate-800 dark:bg-slate-900">
    <label class="sr-only" for="note-search">Search notes</label>
    <input id="note-search" type="search" name="q" value="{{ $search }}" maxlength="80" placeholder="Search my notes" class="rounded-xl border p-3 dark:bg-slate-950">
    <label class="sr-only" for="note-enrollment">Filter by course</label>
    <select id="note-enrollment" name="enrollment" class="rounded-xl border p-3 dark:bg-slate-950">
        <option value="">All my approved courses</option>
        @foreach($enrollments as $enrollment)
            <option value="{{ $enrollment->id }}" @selected((int) $selected === $enrollment->id)>{{ $enrollment->course->title }}</option>
        @endforeach
    </select>
    <button class="rounded-xl bg-blue-700 px-5 py-3 text-sm font-bold text-white">Apply</button>
</form>
<div class="mt-6 space-y-5">
    @forelse($notes as $note)
        <article class="rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-xs font-bold text-blue-700 dark:text-blue-300">
                    {{ $note->enrollment->course->title }} · {{ $note->lesson->title }}
                    @if($note->is_pinned)
                        · Pinned
                    @endif
                </p>
                <a href="{{ route('account.lessons.watch', [$note->enrollment, $note->lesson]) }}" class="text-xs font-bold text-blue-700 dark:text-blue-300">Open lesson</a>
            </div>
            <form method="POST" action="{{ route('account.notes.update', $note) }}" class="mt-4 space-y-3">
                @csrf
                @method('PATCH')
                <label class="block text-xs font-bold" for="note-title-{{ $note->id }}">Note title</label>
                <input id="note-title-{{ $note->id }}" name="title" value="{{ $note->title }}" maxlength="140" required class="w-full rounded-xl border p-3 dark:bg-slate-950">
                <label class="block text-xs font-bold" for="note-body-{{ $note->id }}">Note</label>
                <textarea id="note-body-{{ $note->id }}" name="body" rows="4" maxlength="10000" required class="w-full rounded-xl border p-3 text-sm leading-7 dark:bg-slate-950">{{ $note->body }}</textarea>
                <label class="flex items-center gap-2 text-xs">
                    <input type="hidden" name="is_pinned" value="0">
                    <input type="checkbox" name="is_pinned" value="1" @checked($note->is_pinned)>
                    Pin note
                </label>
                <button class="rounded-xl bg-blue-700 px-5 py-3 text-xs font-bold text-white">Update note</button>
            </form>
            <form method="POST" action="{{ route('account.notes.destroy', $note) }}" class="mt-3">
                @csrf
                @method('DELETE')
                <button class="text-xs font-semibold text-red-600">Delete note</button>
            </form>
        </article>
    @empty
        <p class="rounded-2xl bg-white p-6 text-sm text-slate-500 dark:bg-slate-900">No notes found. Open a lesson from an approved enrollment to create one.</p>
    @endforelse
</div>
<div class="mt-6">{{ $notes->links() }}</div>
@endsection
