@extends('layouts.workspace')
@section('heading', 'Exams & Builder')
@section('heading_action')
    <a class="rounded-xl bg-blue-700 px-4 py-2 text-sm font-semibold text-white" href="{{ route('admin.exams.create') }}">Create exam</a>
@endsection
@section('content')
    <div class="space-y-3">
        @forelse($exams as $exam)
            <article class="workspace-panel flex flex-wrap items-center justify-between gap-4 p-5">
                <div>
                    <h2 class="font-extrabold">{{ $exam->title }}</h2>
                    <p class="mt-1 text-sm">{{ $exam->course->title }} · {{ $exam->type->name }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $exam->published ? 'Published' : 'Draft' }} · {{ count($exam->questions) }} questions · {{ $exam->time_limit }} min</p>
                </div>
                <a class="rounded-xl border border-slate-400 px-4 py-2 text-sm font-semibold" href="{{ route('admin.exams.edit', $exam) }}">Open builder</a>
            </article>
        @empty
            <p class="workspace-panel p-6">Create an exam type, then use the builder to set up a course exam.</p>
        @endforelse
        {{ $exams->links() }}
    </div>
@endsection
