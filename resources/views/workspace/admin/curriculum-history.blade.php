@extends('layouts.workspace')
@section('title','Curriculum Revision History')
@section('heading','Curriculum Revision History')
@section('description','Administrator-only lesson revisions and academic approval evidence. Nothing publishes automatically.')
@section('content')
<a href="{{ route('admin.course-builder.edit',$course) }}" class="inline-flex rounded-full border border-slate-300 px-5 py-3 text-sm font-bold dark:border-slate-700">← Course Builder: {{ $course->title }}</a>
<section class="mt-6 rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="text-xl font-extrabold">Academic readiness checklist</h2>
    <ul class="mt-4 grid gap-3 sm:grid-cols-2">
        @foreach($checklist as $label=>$passed)
            <li class="rounded-xl border p-4 text-sm {{ $passed ? 'text-emerald-700' : 'text-amber-700' }}">{{ $passed ? '✓ Ready' : 'Needs attention' }} · {{ $label }}</li>
        @endforeach
    </ul>
    <p class="mt-4 text-xs text-slate-500">A different administrator must approve publication. Submitted academic content is locked.</p>
</section>
<section class="mt-6 rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="text-xl font-extrabold">Independent review evidence</h2>
    <div class="mt-4 space-y-3">
        @forelse($submissions as $snapshot)
            <article class="rounded-xl bg-slate-50 p-4 dark:bg-slate-950">
                <p class="text-sm font-bold">Submitted {{ $snapshot->created_at?->format('Y-m-d H:i') }} · Author #{{ $snapshot->submitted_by }}</p>
                <p class="mt-1 text-xs text-slate-500">Decision: {{ $snapshot->decision ?? 'Pending' }} · Reviewer #{{ $snapshot->reviewed_by ?? 'Pending' }}</p>
                <p class="mt-1 break-all font-mono text-xs text-slate-500">Academic SHA-256: {{ $snapshot->fingerprint }}</p>
                <ul class="mt-2 text-xs">@foreach($snapshot->checklist as $label=>$passed)<li>{{ $passed ? '✓' : '!' }} {{ $label }}</li>@endforeach</ul>
            </article>
        @empty<p class="text-sm text-slate-500">No recorded submission yet.</p>@endforelse
    </div>
</section>
<section class="mt-6 rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="text-xl font-extrabold">Private lesson revision history</h2>
    <p class="mt-2 text-xs text-slate-500">Append-only history for authorized administrators. No direct restore or publishing action.</p>
    <div class="mt-4 space-y-4">
        @forelse($history as $entry)
            <details class="rounded-2xl border border-slate-200 p-5 dark:border-slate-700">
                <summary class="cursor-pointer text-sm font-bold">{{ $entry->title }} · Revision {{ $entry->snapshot_number }} ({{ $entry->action }}) · {{ $entry->created_at?->format('Y-m-d H:i') }}</summary>
                <p class="mt-3 text-xs text-slate-500">Editor: {{ $entry->editor?->name ?? 'Former administrator' }} · {{ $entry->duration_minutes }} min</p>
                <p class="mt-3 whitespace-pre-line break-words rounded-xl bg-slate-50 p-4 text-sm leading-7 dark:bg-slate-950">{{ $entry->content }}</p>
            </details>
        @empty<p class="text-sm text-slate-500">No lesson changes tracked yet.</p>@endforelse
    </div>
    <div class="mt-5">{{ $history->links() }}</div>
</section>
@endsection
