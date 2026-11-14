@extends('layouts.workspace')
@section('title', $heading)
@section('heading', $heading)
@section('description', $description)

@section('content')
<x-dashboard-showcase :showcase="$showcase" :role="$role" />
<div class="mt-8 mb-4 flex flex-wrap items-center justify-between gap-3">
    <h2 class="text-lg font-extrabold tracking-tight text-[#102f5c] dark:text-[#f8cf7a]">{{ __('dashboard.metrics_label') }}</h2>
    <span class="text-xs text-slate-500 dark:text-slate-400">{{ __('dashboard.badge_no_data') }}</span>
</div>
{{-- Every summary uses real authorized database records and an existing destination. --}}
<div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 xl:grid-cols-5">
    @foreach(array_slice($stats, 0, 15) as $stat)
        <x-dashboard-metric-card :stat="$stat" />
    @endforeach
</div>

@if($role === 'student')
    <div class="mt-7 grid gap-5 xl:grid-cols-[1.2fr_.8fr]">
        <section class="premium-card rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-7">
            <span class="premium-corner-icon" aria-hidden="true"><x-workspace-icon name="book-open" /></span>
            <div class="premium-card-content flex flex-wrap items-start justify-between gap-3">
                <div><h2 class="text-lg font-extrabold">{{ __('dashboard.course_progress') }}</h2><p class="mt-1 text-xs leading-6 text-slate-500">{{ __('dashboard.self_reported') }}</p></div>
                <a href="{{ route('account.enrollments.index') }}" class="rounded-full border border-blue-200 px-4 py-2 text-xs font-bold text-blue-700 transition hover:bg-blue-50 dark:border-blue-900 dark:text-blue-400">{{ __('workspace.my_learning') }}</a>
            </div>
            <div class="mt-5 space-y-4">
            @forelse($activeCourses as $enrollment)
                @php
                    $total = max(0, (int)($enrollment->course?->lessons_count ?? 0));
                    $done = min($total, max(0,(int)$enrollment->completed_lessons_count));
                    $percent = $total ? (int) round($done*100/$total) : 0;
                @endphp
                <article class="flex flex-wrap items-center gap-4 rounded-2xl border border-slate-100 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950">
                    <div class="relative grid h-[82px] w-[82px] shrink-0 place-items-center">
                        <svg viewBox="0 0 100 100" class="absolute inset-0 h-full w-full -rotate-90" aria-hidden="true">
                            <circle cx="50" cy="50" r="41" fill="none" stroke="currentColor" stroke-width="10" class="text-slate-200 dark:text-slate-800" />
                            <circle cx="50" cy="50" r="41" fill="none" stroke="currentColor" stroke-width="10" pathLength="100" stroke-dasharray="{{ $percent }} 100" stroke-linecap="round" class="text-blue-700 dark:text-blue-400" />
                        </svg>
                        <span class="text-lg font-extrabold tabular-nums" aria-label="{{ $percent }} percent {{ __('dashboard.completion') }}">{{ $percent }}%</span>
                    </div>
                    <div class="min-w-[140px] flex-1">
                        <h3 class="text-sm font-extrabold leading-6"><a href="{{ route('account.enrollments.show',$enrollment) }}" class="rounded hover:underline focus-visible:outline-2 focus-visible:outline-blue-600">{{ $enrollment->course?->title }}</a></h3>
                        <p class="mt-1 text-xs text-slate-500">{{ $done }} / {{ $total }} {{ __('dashboard.lessons_completed') }}</p>
                        <a href="{{ route('account.enrollments.show',$enrollment) }}" class="mt-2 inline-flex items-center gap-1 text-xs font-extrabold text-blue-700 hover:underline dark:text-blue-400">{{ __('dashboard.resume_learning') }}</a>
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-200 p-6 text-sm leading-7 text-slate-500 dark:border-slate-800">
                    {{ __('dashboard.no_courses') }}
                    <div class="mt-4"><a href="{{ route('account.courses.index') }}" class="inline-flex rounded-full bg-blue-700 px-5 py-2.5 text-xs font-bold text-white">{{ __('dashboard.browse_catalogue') }}</a></div>
                </div>
            @endforelse
            </div>
        </section>
        <section class="premium-card rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-7">
            <span class="premium-corner-icon" aria-hidden="true"><x-workspace-icon name="calendar" /></span>
            <div class="premium-card-content flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-lg font-extrabold">{{ __('dashboard.upcoming_work') }}</h2>
            </div>
            <ol class="mt-5 max-h-[470px] space-y-3 overflow-y-auto">
            @forelse($upcoming as $assignment)
                @php
                    $today = $assignment->due_at->isToday();
                    $thisWeek = $assignment->due_at->lte(now()->addDays(7));
                @endphp
                <li>
                    <a href="{{ route('account.homework.show',$assignment) }}" class="flex items-start gap-3 rounded-2xl border border-slate-100 p-4 transition hover:border-blue-300 dark:border-slate-800">
                        <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full {{ $today ? 'bg-red-500' : ($thisWeek ? 'bg-amber-500' : 'bg-blue-500') }}"></span>
                        <span class="min-w-0 flex-1"><strong class="block text-sm leading-6">{{ $assignment->title }}</strong><span class="block text-xs text-slate-500">{{ $assignment->course?->title }}</span>
                        <span class="mt-2 block text-xs font-semibold {{ $today ? 'text-red-700 dark:text-red-400' : ($thisWeek ? 'text-amber-700 dark:text-amber-400' : 'text-blue-700 dark:text-blue-400') }}">{{ $today ? __('dashboard.today') : ($thisWeek ? __('dashboard.this_week') : __('dashboard.later')) }} · {{ $assignment->due_at->format('M j, H:i') }}</span></span>
                    </a>
                </li>
            @empty
                <li class="rounded-2xl bg-slate-50 p-6 text-sm leading-7 text-slate-500 dark:bg-slate-950">{{ __('dashboard.no_deadlines') }}</li>
            @endforelse
            </ol>
        </section>
    </div>
    <section class="premium-card mt-5 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-7">
        <span class="premium-corner-icon" aria-hidden="true"><x-workspace-icon name="chart-line" /></span>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div><h2 class="text-lg font-extrabold">{{ __('dashboard.practice_subjects') }}</h2><p class="mt-1 text-xs leading-6 text-slate-500">{{ __('dashboard.practice_only') }} · {{ __('dashboard.activity_not_attendance') }}</p></div>
            <a href="{{ route('account.practice.history') }}" class="rounded-full border border-blue-200 px-4 py-2 text-xs font-bold text-blue-700 hover:bg-blue-50 dark:border-blue-900 dark:text-blue-400">{{ __('dashboard.practice_attempts') }}</a>
        </div>
        @if(count($performance) > 0)
            @php
                $scoreItems = array_values($performance);
                $count = count($scoreItems);
                $radarPoints = [];
                if ($count >= 3) {
                    foreach($scoreItems as $index => $score) {
                        $angle = deg2rad(-90 + 360*$index/$count);
                        $radius = (float)$score['value']/100*74;
                        $radarPoints[] = round(100+cos($angle)*$radius,2).','.round(100+sin($angle)*$radius,2);
                    }
                }
            @endphp
            <div class="mt-5 grid items-center gap-5 {{ $count >= 3 ? 'lg:grid-cols-[220px_1fr]' : '' }}">
                @if($count >= 3)
                    <svg viewBox="0 0 200 200" role="img" aria-label="{{ __('dashboard.practice_subjects') }}" class="mx-auto aspect-square w-52 max-w-full">
                        <title>{{ __('dashboard.practice_subjects') }}</title>
                        @foreach([74,49,25] as $radius)<circle cx="100" cy="100" r="{{ $radius }}" fill="none" stroke="currentColor" stroke-width="1" class="text-slate-200 dark:text-slate-700" />@endforeach
                        @foreach($scoreItems as $index=>$score)
                            @php $angle = deg2rad(-90+360*$index/$count); @endphp
                            <line x1="100" y1="100" x2="{{ round(100+cos($angle)*74,2) }}" y2="{{ round(100+sin($angle)*74,2) }}" stroke="currentColor" stroke-width="1" class="text-slate-200 dark:text-slate-700" />
                        @endforeach
                        <polygon points="{{ implode(' ',$radarPoints) }}" fill="#2563eb" fill-opacity=".17" stroke="#2563eb" stroke-width="2.5" stroke-linejoin="round" />
                    </svg>
                @endif
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach($performance as $category=>$score)
                    <div>
                        <div class="flex items-center justify-between gap-2"><span class="min-w-0 truncate text-sm font-semibold">{{ $category }}</span><span class="shrink-0 text-sm font-extrabold tabular-nums">{{ $score['value'] }}%</span></div>
                        <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"><div class="h-full rounded-full bg-blue-600" style="width:{{ max(0,min(100,$score['value'])) }}%"></div></div>
                        <p class="mt-1 text-[11px] text-slate-500">{{ $score['sample'] }} {{ __('dashboard.sample_count') }}</p>
                    </div>
                    @endforeach
                </div>
            </div>
        @else
            <p class="mt-5 rounded-2xl bg-slate-50 p-6 text-sm leading-7 text-slate-500 dark:bg-slate-950">{{ __('dashboard.no_practice') }}</p>
        @endif
    </section>
@elseif($role === 'admin')
    <div class="mt-7 grid gap-5 xl:grid-cols-2">
        <section class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-extrabold">{{ __('dashboard.trend') }}</h2>
                <span class="grid h-10 w-10 place-items-center rounded-2xl bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300"><x-workspace-icon name="activity" /></span>
            </div>
            @php $maxCount = max(1,max(array_column($enrollmentTrend,'count'))); $totalRecent = array_sum(array_column($enrollmentTrend,'count')); @endphp
            @if($totalRecent)
                <div class="mt-6 h-[172px]"><svg viewBox="0 0 350 130" role="img" aria-label="{{ __('dashboard.trend') }}" class="h-full w-full" preserveAspectRatio="xMidYMid meet">
                    <title>{{ __('dashboard.trend') }}</title>
                    @foreach($enrollmentTrend as $index=>$day)
                        @php $height = (int)round($day['count']/$maxCount*92); @endphp
                        <rect x="{{ 13+$index*49 }}" y="{{ 104-$height }}" width="31" height="{{ max($day['count']?3:0,$height) }}" rx="6" fill="#2563eb" fill-opacity="{{ $index===6 ? 1 : .65 }}" >
                            <title>{{ $day['date'] }}: {{ $day['count'] }}</title>
                        </rect>
                        <text x="{{ 28+$index*49 }}" y="124" font-size="9" fill="currentColor" text-anchor="middle">{{ substr($day['date'],5) }}</text>
                    @endforeach
                </svg></div>
            @else
                <p class="mt-7 rounded-2xl bg-slate-50 p-6 text-sm leading-7 text-slate-500 dark:bg-slate-950">{{ __('dashboard.no_trend') }}</p>
            @endif
            <p class="mt-4 text-xs text-slate-500">{{ __('dashboard.activity_not_attendance') }}</p>
        </section>
        <section class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-lg font-extrabold">{{ __('dashboard.review_queue') }}</h2>
            <div class="mt-5 divide-y divide-slate-100 dark:divide-slate-800">
                @foreach($reviewQueue as $queue)
                    <a href="{{ $queue['url'] }}" class="flex items-center justify-between gap-3 py-3.5 text-sm transition hover:text-blue-700 dark:hover:text-blue-400">
                        <span class="font-semibold">{{ $queue['label'] }}</span>
                        <span class="inline-flex items-center gap-3"><strong class="text-lg tabular-nums">{{ \App\Support\DashboardNumber::compact($queue['value']) }}</strong><x-workspace-icon name="arrow-up-right" class="h-4 w-4 text-slate-400" /></span>
                    </a>
                @endforeach
            </div>
            <p class="mt-5 text-xs text-slate-500">{{ __('dashboard.no_payments') }}</p>
        </section>
    </div>
@elseif($role === 'instructor')
    <div class="mt-7 grid gap-5 xl:grid-cols-2">
        <section class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between gap-2"><h2 class="text-lg font-extrabold">{{ __('dashboard.grading_queue') }}</h2><span class="grid h-10 w-10 place-items-center rounded-2xl bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300"><x-workspace-icon name="pen" /></span></div>
            <div class="mt-5 divide-y divide-slate-100 dark:divide-slate-800">
            @forelse($gradingQueue as $submission)
                <a href="{{ route('instructor.grading.show',$submission) }}" class="flex flex-wrap items-center justify-between gap-3 py-4 text-sm hover:text-blue-700 dark:hover:text-blue-400">
                    <span><strong class="block">{{ $submission->assignment?->title }}</strong><span class="mt-1 block text-xs text-slate-500">{{ $submission->enrollment?->student?->name }}</span></span>
                    <span class="text-xs text-slate-500">{{ $submission->submitted_at?->format('M j') }}</span>
                </a>
            @empty<p class="rounded-2xl bg-slate-50 p-5 text-sm text-slate-500 dark:bg-slate-950">{{ __('dashboard.no_grading') }}</p>@endforelse
            </div>
            <div class="mt-5 flex justify-end"><a href="{{ route('instructor.grading.index') }}" class="rounded-full border border-blue-300 px-4 py-2.5 text-xs font-bold text-blue-700 dark:border-blue-900 dark:text-blue-400">{{ __('dashboard.open_gradebook') }}</a></div>
        </section>
        <section class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-lg font-extrabold">{{ __('dashboard.teacher_deadlines') }}</h2>
            <div class="mt-5 divide-y divide-slate-100 dark:divide-slate-800">
            @forelse($upcoming as $assignment)
                <a href="{{ route('instructor.courses.show',$assignment->course) }}" class="flex flex-wrap items-center justify-between gap-3 py-4 text-sm hover:text-blue-700 dark:hover:text-blue-400">
                    <span><strong class="block">{{ $assignment->title }}</strong><span class="mt-1 block text-xs text-slate-500">{{ $assignment->course?->title }}</span></span>
                    <span class="text-xs font-bold text-amber-700 dark:text-amber-400">{{ $assignment->due_at?->format('M j, H:i') }}</span>
                </a>
            @empty<p class="rounded-2xl bg-slate-50 p-5 text-sm text-slate-500 dark:bg-slate-950">{{ __('dashboard.no_teacher_deadlines') }}</p>@endforelse
            </div>
        </section>
    </div>
@else
    <div class="mt-7 grid gap-5 xl:grid-cols-2">
        <section class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-lg font-extrabold">{{ __('dashboard.view_api') }}</h2>
            <p class="mt-3 text-sm leading-7 text-slate-500">The connected API currently provides read-only catalogue, course and CEFR routes. Requests require authenticated developer tokens, permission scopes and throttling.</p>
            <div class="mt-5 flex justify-end"><a href="{{ route('developer.apis') }}" class="inline-flex items-center gap-2 rounded-full bg-blue-700 px-5 py-3 text-sm font-bold text-white">API reference <x-workspace-icon name="arrow-up-right" class="h-4 w-4" /></a></div>
        </section>
        <section class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-lg font-extrabold">{{ __('dashboard.no_traffic') }}</h2>
            <p class="mt-3 text-sm leading-7 text-slate-500">Access-token statistics represent your actual stored tokens. Historical request telemetry is not collected; no made-up graph is shown.</p>
            <div class="mt-5 flex justify-end"><a href="{{ route('developer.usage') }}" class="rounded-full border border-blue-300 px-5 py-3 text-sm font-bold text-blue-700 dark:border-blue-900 dark:text-blue-400">Usage & limits</a></div>
        </section>
    </div>
@endif
@endsection
