@extends('layouts.workspace')
@section('title', 'Review & Approvals')
@section('heading', 'Review & Approvals')
@section('description', 'Record accountable decisions on pending curriculum, appearance revisions and student enrollment requests. All actions are audited.')
@section('content')
<nav aria-label="Review queues" class="mb-6 flex flex-wrap gap-3">
    @foreach(['courses'=>'Courses','appearance'=>'Appearance','enrollments'=>'Enrollments'] as $key=>$label)
        <a href="{{ route('admin.reviews.index',['focus'=>$key]) }}" @if($focus===$key)aria-current="page"@endif class="rounded-full border px-5 py-2.5 text-sm font-semibold {{ $focus===$key ? 'border-blue-700 bg-blue-700 text-white' : 'border-slate-300 bg-white dark:border-slate-700 dark:bg-slate-900' }}">{{ $label }}</a>
    @endforeach
</nav>
@if($focus === 'courses')
    <div class="space-y-5">
        @forelse($courses as $course)
        <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div><p class="text-xs font-bold uppercase tracking-widest text-blue-700 dark:text-blue-400">Course #{{ $course->id }}</p><h2 class="mt-2 text-xl font-extrabold">{{ $course->title }}</h2><p class="mt-2 text-sm text-slate-500">{{ $course->category?->name }} · submitted {{ $course->submitted_at?->format('Y-m-d H:i') }}</p></div>
                <a href="{{ route('admin.course-builder.edit', $course) }}" class="rounded-full border border-slate-300 px-5 py-2.5 text-sm font-semibold dark:border-slate-700">Inspect curriculum</a>
            </div>
            <p class="mt-4 text-sm leading-7 text-slate-600 dark:text-slate-400">{{ $course->summary }}</p>
            <p class="mt-3 text-xs font-semibold text-slate-500">Created by account #{{ $course->created_by ?? 'Unknown' }} · Reviewer must be a different administrator.</p>
            @if($course->created_by === auth()->id())
                <p class="mt-4 text-sm font-semibold text-amber-700 dark:text-amber-400">You submitted this course. Another administrator must decide.</p>
            @else
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <form method="POST" action="{{ route('admin.reviews.course',$course) }}" class="flex items-end gap-2">@csrf<input type="hidden" name="decision" value="approve"><button type="submit" class="w-full rounded-full bg-emerald-700 px-5 py-3 text-sm font-bold text-white hover:bg-emerald-800">Approve & publish</button></form>
                    <form method="POST" action="{{ route('admin.reviews.course',$course) }}" class="flex flex-col gap-2">@csrf<input type="hidden" name="decision" value="reject"><label for="course-note-{{ $course->id }}" class="text-xs font-bold">Rejection reason</label><textarea id="course-note-{{ $course->id }}" name="note" rows="2" maxlength="500" required class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"></textarea><button type="submit" class="self-end rounded-full border border-red-300 px-5 py-3 text-sm font-bold text-red-700">Reject</button></form>
                </div>
            @endif
        </article>
        @empty
        <p class="rounded-3xl border border-slate-200 bg-white p-8 text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-900">No courses awaiting review.</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $courses->appends(['focus'=>'courses'])->links() }}</div>
@elseif($focus === 'appearance')
    <div class="space-y-5">
        @forelse($appearances as $revision)
        <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="text-xs font-bold uppercase tracking-widest text-blue-700 dark:text-blue-400">Appearance revision #{{ $revision->id }}</p>
            <h2 class="mt-2 text-xl font-extrabold capitalize">{{ $revision->section }}</h2>
            <p class="mt-2 text-sm text-slate-500">Submitted by {{ $revision->creator?->name }} · {{ $revision->submitted_at?->format('Y-m-d H:i') }}</p>
            <dl class="mt-5 grid gap-2 rounded-2xl bg-slate-50 p-5 dark:bg-slate-950">
            @foreach($revision->settings as $key=>$value)
                <div class="flex flex-wrap gap-2 text-sm"><dt class="min-w-32 font-bold">{{ ucwords(str_replace('_',' ',$key)) }}</dt><dd class="min-w-0 flex-1 break-words text-slate-500">{{ is_bool($value) ? ($value ? 'Enabled' : 'Disabled') : $value }}</dd></div>
            @endforeach
            </dl>
            @if($revision->created_by === auth()->id())
                <p class="mt-4 text-sm font-semibold text-amber-700 dark:text-amber-400">Another administrator must review your revision.</p>
            @else
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <form method="POST" action="{{ route('admin.reviews.appearance',$revision) }}">@csrf<input type="hidden" name="decision" value="approve"><button type="submit" class="w-full rounded-full bg-emerald-700 px-5 py-3 text-sm font-bold text-white">Approve & activate</button></form>
                    <form method="POST" action="{{ route('admin.reviews.appearance',$revision) }}" class="flex flex-col gap-2">@csrf<input type="hidden" name="decision" value="reject"><label class="text-xs font-bold" for="appearance-note-{{ $revision->id }}">Rejection reason</label><textarea id="appearance-note-{{ $revision->id }}" name="note" required maxlength="500" rows="2" class="rounded-xl border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"></textarea><button type="submit" class="self-end rounded-full border border-red-300 px-5 py-3 text-sm font-bold text-red-700">Reject</button></form>
                </div>
            @endif
        </article>
        @empty
        <p class="rounded-3xl border border-slate-200 bg-white p-8 text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-900">No appearance changes awaiting review.</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $appearances->appends(['focus'=>'appearance'])->links() }}</div>
@else
    <div class="space-y-5">
        @forelse($enrollments as $enrollment)
        <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-xl font-extrabold">{{ $enrollment->course?->title }}</h2>
            <p class="mt-2 text-sm text-slate-500">Student: {{ $enrollment->student?->name }} · {{ $enrollment->student?->email }}</p>
            <p class="mt-1 text-xs text-slate-500">Requested {{ $enrollment->created_at?->format('Y-m-d H:i') }}</p>
            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                <form method="POST" action="{{ route('admin.reviews.enrollment',$enrollment) }}">@csrf<input type="hidden" name="decision" value="approve"><button type="submit" class="w-full rounded-full bg-emerald-700 px-5 py-3 text-sm font-bold text-white">Approve enrollment</button></form>
                <form method="POST" action="{{ route('admin.reviews.enrollment',$enrollment) }}" class="flex flex-col gap-2">@csrf<input type="hidden" name="decision" value="decline"><label class="text-xs font-bold" for="enrollment-note-{{ $enrollment->id }}">Decline reason</label><textarea id="enrollment-note-{{ $enrollment->id }}" name="note" maxlength="500" rows="2" required class="rounded-xl border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"></textarea><button type="submit" class="self-end rounded-full border border-red-300 px-5 py-3 text-sm font-bold text-red-700">Decline</button></form>
            </div>
        </article>
        @empty
            <p class="rounded-3xl border border-slate-200 bg-white p-8 text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-900">No enrollment requests awaiting review.</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $enrollments->appends(['focus'=>'enrollments'])->links() }}</div>
@endif
@endsection
