@extends('layouts.workspace')
@section('title', 'Review & Approvals')
@section('heading', 'Review & Approvals')
@section('workspace_compact', 'true')
@push('workspace_styles')
    <link rel="stylesheet" href="{{ asset('css/admin-review-queue.css') }}?v=2">
    @if($focus === 'courses')
        <script defer src="{{ asset('js/admin-review-queue.js') }}?v=2"></script>
    @endif
@endpush

@section('content')
<header class="review-header">
    <h1>Review &amp; Approvals</h1>
</header>
<nav class="review-nav" aria-label="Review queues">
    @foreach(['courses' => ['Courses', $courses], 'appearance' => ['Appearance', $appearances], 'enrollments' => ['Enrollments', $enrollments]] as $key => [$label, $queue])
        <a href="{{ route('admin.reviews.index', ['focus' => $key, 'lang' => app()->getLocale()]) }}" @if($focus === $key) aria-current="page" @endif>
            {{ $label }} <span>{{ $queue->total() }}</span>
        </a>
    @endforeach
</nav>

@if($focus === 'courses')
    @include('workspace.admin.partials.course-review-queue')
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
    <div class="mt-6">{{ $appearances->appends(['focus' => 'appearance', 'lang' => app()->getLocale()])->links() }}</div>
@else
    <div class="space-y-5">
        @forelse($enrollments as $enrollment)
        <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-xl font-extrabold">{{ $enrollment->course?->title }}</h2>
            <p class="mt-2 text-sm text-slate-500">Student: {{ $enrollment->student?->name }} · {{ $enrollment->student?->email }}</p>
            <p class="mt-1 text-xs text-slate-500">Requested {{ $enrollment->created_at?->format('Y-m-d H:i') }}</p>
            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                <form method="POST" action="{{ route('admin.reviews.enrollment',$enrollment) }}">@csrf<input type="hidden" name="decision" value="approve">
                    @if($quote = $enrollment->pricing_quote)
                        <p class="mb-2 text-sm font-semibold">
                            {{ $quote['model'] === 'paid' ? \App\Support\CoursePricing::formatAmount($quote['amount_minor'], $quote['currency']) : $quote['plan'].' membership' }}
                        </p>
                        <label class="mb-3 block text-sm">Verified payment or membership reference
                            <input name="pricing_reference" required maxlength="160" class="mt-1 block w-full rounded-lg border px-3 py-2">
                        </label>
                    @endif
                    <button type="submit" class="w-full rounded-full bg-emerald-700 px-5 py-3 text-sm font-bold text-white">Approve enrollment</button></form>
                <form method="POST" action="{{ route('admin.reviews.enrollment',$enrollment) }}" class="flex flex-col gap-2">@csrf<input type="hidden" name="decision" value="decline"><label class="text-xs font-bold" for="enrollment-note-{{ $enrollment->id }}">Decline reason</label><textarea id="enrollment-note-{{ $enrollment->id }}" name="note" maxlength="500" rows="2" required class="rounded-xl border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"></textarea><button type="submit" class="self-end rounded-full border border-red-300 px-5 py-3 text-sm font-bold text-red-700">Decline</button></form>
            </div>
        </article>
        @empty
            <p class="rounded-3xl border border-slate-200 bg-white p-8 text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-900">No enrollment requests awaiting review.</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $enrollments->appends(['focus' => 'enrollments', 'lang' => app()->getLocale()])->links() }}</div>
@endif
@endsection
