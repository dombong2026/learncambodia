@extends('layouts.workspace')
@section('title', 'Academic Evidence Review')
@section('heading', 'Academic Evidence Review')
@section('description', 'An internal evidence-check workflow for future school qualifications. No certificates, credential IDs, PDFs or official exam claims can be issued here.')
@section('content')
<div class="max-w-5xl rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm leading-7 text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300">
    <strong>Certificate issuance disabled.</strong> Lesson completion is self-reported, practice quiz results are unofficial, and instructor scores are internal. Reviewing evidence does not certify a learner, establish CEFR proficiency, or issue an award. Academic policy, identity checks, assessment validation and independent security acceptance are outstanding.
</div>
<section class="mt-7">
    <h2 class="mb-5 text-xl font-extrabold">Recorded learning evidence</h2>
    <div class="overflow-x-auto rounded-3xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <table class="min-w-[1030px] w-full text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-widest text-slate-500 dark:border-slate-800 dark:bg-slate-900">
                <tr><th class="px-5 py-4">Student</th><th class="px-5 py-4">Course</th><th class="px-5 py-4">Lessons marked</th><th class="px-5 py-4">Practice passes</th><th class="px-5 py-4">Graded homework</th><th class="px-5 py-4 text-right">Action</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            @forelse($enrollments as $enrollment)
                <tr>
                    <td class="px-5 py-4 font-bold">{{ $enrollment->student?->name }}</td>
                    <td class="px-5 py-4">{{ $enrollment->course?->title }} <span class="text-xs text-slate-500">v{{ $enrollment->course?->revision_number }}</span></td>
                    <td class="px-5 py-4 tabular-nums">{{ $enrollment->completed_lessons_count }} / {{ $enrollment->course?->lessons_count }}</td>
                    <td class="px-5 py-4 tabular-nums">{{ $enrollment->passed_practice_count }}</td>
                    <td class="px-5 py-4 tabular-nums">{{ $enrollment->graded_homework_count }}</td>
                    <td class="px-5 py-4 text-right">
                        <form method="POST" action="{{ route('admin.academic.request',$enrollment) }}">@csrf
                            <button type="submit" class="rounded-full border border-blue-400 px-4 py-2 text-xs font-bold text-blue-700 dark:text-blue-400">Request evidence review</button>
                        </form>
                    </td>
                </tr>
            @empty<tr><td colspan="6" class="px-5 py-12 text-center text-slate-500">No active approved enrollments with evidence exist yet.</td></tr>@endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-5">{{ $enrollments->links() }}</div>
</section>
<section class="mt-10">
    <h2 class="mb-2 text-xl font-extrabold">Independent evidence decisions</h2>
    <p class="mb-5 text-sm leading-7 text-slate-500">A different administrator must compare the recorded snapshot with the underlying academic evidence. “Reviewed” means evidence was examined; it is not certificate approval or issuance.</p>
    <div class="space-y-5">
    @forelse($reviews as $review)
        <article class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div><p class="text-xs font-bold uppercase tracking-widest text-blue-700 dark:text-blue-400">Evidence review #{{ $review->id }}</p><h3 class="mt-2 text-lg font-extrabold">{{ $review->enrollment?->student?->name }} · {{ $review->enrollment?->course?->title }}</h3></div>
                <span class="rounded-full bg-slate-100 px-4 py-2 text-xs font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ ucwords(str_replace('_',' ', $review->status)) }}</span>
            </div>
            <dl class="mt-5 grid gap-3 rounded-2xl bg-slate-50 p-5 text-sm dark:bg-slate-950 sm:grid-cols-2">
                @foreach($review->evidence_snapshot as $key=>$value)
                    <div><dt class="text-xs font-bold uppercase tracking-widest text-slate-500">{{ str_replace('_',' ',$key) }}</dt><dd class="mt-1 font-semibold tabular-nums">{{ $value }}</dd></div>
                @endforeach
            </dl>
            @if($review->review_note)<p class="mt-4 text-sm leading-7 text-slate-600 dark:text-slate-400">Review note: {{ $review->review_note }}</p>@endif
            @if($review->status === 'pending_review')
                @if($review->requested_by === auth()->id())<p class="mt-5 text-sm font-semibold text-amber-700 dark:text-amber-400">Another administrator must review your request.</p>
                @else
                <div class="mt-5 grid gap-4 lg:grid-cols-2">
                    @foreach(['reviewed'=>'Record evidence reviewed','needs_revision'=>'Request evidence corrections'] as $decision=>$label)
                        <form method="POST" action="{{ route('admin.academic.review',$review) }}" class="space-y-3">@csrf
                            <input type="hidden" name="decision" value="{{ $decision }}">
                            <label for="evidence-note-{{ $review->id }}-{{ $decision }}" class="block text-xs font-bold">{{ $decision==='reviewed' ? 'Academic reviewer notes' : 'Required corrections' }}</label>
                            <textarea name="note" id="evidence-note-{{ $review->id }}-{{ $decision }}" minlength="10" maxlength="500" required rows="3" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950"></textarea>
                            <div class="flex justify-end"><button type="submit" class="rounded-full {{ $decision==='reviewed' ? 'bg-blue-700 text-white' : 'border border-amber-400 text-amber-700 dark:text-amber-400' }} px-5 py-3 text-sm font-bold">{{ $label }}</button></div>
                        </form>
                    @endforeach
                </div>
                @endif
            @endif
        </article>
    @empty
        <p class="rounded-3xl border border-slate-200 bg-white p-8 text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-900">No academic evidence requests yet.</p>
    @endforelse
    </div>
    <div class="mt-6">{{ $reviews->links() }}</div>
</section>
@endsection
