@props(['showcase','role'])
@php
    $heroIcon = match($role) {
        'admin' => 'chart-column',
        'instructor' => 'presentation',
        'student' => 'book-open',
        'developer' => 'terminal',
        default => 'layout',
    };
@endphp
<section data-dashboard-showcase aria-label="{{ __('dashboard.feature_overview') }}" class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="min-w-0">
            <p class="mb-1 text-[11px] font-extrabold uppercase tracking-[.16em] text-[#b07918] dark:text-[#f5cd71]">{{ __('dashboard.feature_overview') }}</p>
            <h2 class="learning-hero-title text-[1.75rem] font-black text-slate-950 dark:text-slate-50 sm:text-3xl xl:text-[2.05rem]">{{ $showcase['greeting'] }}</h2>
        </div>
        <a href="{{ $showcase['feature_url'] }}" class="inline-flex shrink-0 items-center gap-2 rounded-full border border-[#d8e9d9] bg-[#edf8ee] px-4 py-2.5 text-xs font-extrabold text-[#227248] transition hover:border-[#8dc89d] hover:bg-[#def1df] focus-visible:outline-2 focus-visible:outline-[#227248] dark:border-[#356c55] dark:bg-[#1e4938] dark:text-[#aff0c2]">
            <span class="h-2.5 w-2.5 rounded-full bg-[#31a064]" aria-hidden="true"></span>
            <span>{{ $showcase['status'] }}: <span class="tabular-nums">{{ \App\Support\DashboardNumber::compact($showcase['status_count']) }}</span></span>
            <x-workspace-icon name="arrow-up-right" class="h-3.5 w-3.5" />
        </a>
    </div>
    <div class="grid items-stretch gap-5 xl:grid-cols-[1.26fr_1fr_1fr]">
        <section class="learning-dashboard-panel learning-dashboard-navy flex min-w-0 flex-col p-6 sm:p-7" aria-label="{{ $showcase['feature_title'] }}">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-[11px] font-extrabold uppercase tracking-[.12em] text-[#ffd476]">{{ __('dashboard.feature_overview') }}</p>
                    <h3 class="mt-2 text-lg font-black leading-6 text-white sm:text-xl">{{ $showcase['feature_title'] }}</h3>
                    <p class="mt-1 text-xs leading-5 text-[#b7cce5]">{{ $showcase['feature_description'] }}</p>
                </div>
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-white/10 text-[#f7bc45]"><x-workspace-icon :name="$heroIcon" class="h-6 w-6" /></span>
            </div>
            @if(count($showcase['rings']) > 0)
                <div class="my-7 grid flex-1 grid-cols-{{ count($showcase['rings']) > 1 ? 2 : 1 }} items-start justify-items-center gap-4">
                    @foreach($showcase['rings'] as $ring)
                        <a href="{{ $ring['url'] }}" class="group flex min-w-0 w-full flex-col items-center rounded-2xl px-1 text-center transition hover:bg-white/5 focus-visible:outline-2 focus-visible:outline-[#ffca65]">
                            <p class="mb-3 min-h-[38px] w-full break-words text-xs font-bold leading-[1.55] text-[#e7eef9]">{{ $ring['label'] }}</p>
                            <div class="relative grid h-24 w-24 shrink-0 place-items-center sm:h-[116px] sm:w-[116px]">
                                <svg viewBox="0 0 100 100" class="learning-progress-ring absolute inset-0 h-full w-full -rotate-90" role="img" aria-label="{{ $ring['label'] }}: {{ $ring['percent'] === null ? __('dashboard.unavailable') : $ring['percent'].'%' }}">
                                    <circle cx="50" cy="50" r="40" fill="none" stroke="#4c678b" stroke-width="10" />
                                    @if($ring['percent'] !== null)
                                        <circle cx="50" cy="50" r="40" fill="none" stroke="#f7bb42" stroke-width="10" stroke-linecap="round" pathLength="100" stroke-dasharray="{{ $ring['percent'] }} 100" />
                                    @endif
                                </svg>
                                <strong class="text-[1.55rem] font-black tabular-nums tracking-tight text-white sm:text-[1.75rem]">{{ $ring['percent'] === null ? '—' : $ring['percent'].'%' }}</strong>
                            </div>
                            <span class="mt-3 min-h-[30px] text-xs font-semibold tabular-nums leading-5 text-[#b8cfe8]">{{ $ring['caption'] }}</span>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="my-7 flex flex-1 flex-col items-center justify-center rounded-2xl border border-dashed border-white/25 px-5 py-7 text-center">
                    <x-workspace-icon name="book-open" class="h-9 w-9 text-[#f7bb42]" />
                    <p class="mt-3 text-sm font-bold text-white">{{ __('dashboard.no_course_current') }}</p>
                    <p class="mt-2 text-xs leading-6 text-[#b8cfe8]">{{ __('dashboard.no_course_detail') }}</p>
                </div>
            @endif
            <div class="flex justify-center">
                <a href="{{ $showcase['feature_url'] }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#f4f6fb] px-5 py-2.5 text-xs font-black text-[#0c2d5b] transition hover:bg-[#ffdf9c] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#ffd474]">
                    {{ $showcase['feature_link'] }}
                    <x-workspace-icon name="arrow-up-right" class="h-4 w-4" />
                </a>
            </div>
        </section>

        <section class="learning-dashboard-panel flex min-w-0 flex-col p-6 sm:p-7" aria-label="{{ $showcase['timeline_title'] }}">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[.1em] text-slate-500 dark:text-slate-400">{{ __('dashboard.recent_activity') }}</p>
                    <h3 class="mt-2 text-lg font-black leading-6 text-[#0d2d5b] dark:text-[#fce0a7]">{{ $showcase['timeline_title'] }}</h3>
                </div>
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-[#f0f4fa] text-[#1d467e] dark:bg-[#214067] dark:text-[#f7c36b]"><x-workspace-icon name="calendar" class="h-5 w-5" /></span>
            </div>
            <ol class="mt-5 flex-1 space-y-1">
                @forelse($showcase['timeline'] as $item)
                    <li class="relative border-l-2 border-[#e9ecf2] py-2 pl-5 dark:border-[#365274]">
                        <span class="absolute -left-[6px] top-4 h-2.5 w-2.5 rounded-full border-2 border-white dark:border-[#122849] {{ $item['urgency'] === 'urgent' ? 'bg-red-500' : ($item['urgency'] === 'soon' ? 'bg-[#f4b73d]' : 'bg-[#6d92be]') }}" aria-hidden="true"></span>
                        <a href="{{ $item['url'] }}" class="group block rounded-xl px-2 py-1 transition hover:bg-[#f5f8fc] focus-visible:outline-2 focus-visible:outline-[#265da8] dark:hover:bg-white/5">
                            <p class="line-clamp-2 text-sm font-extrabold leading-6 text-slate-900 group-hover:text-[#1e5089] dark:text-white">{{ $item['label'] }}</p>
                            <p class="mt-1 line-clamp-1 text-xs text-slate-500 dark:text-slate-400">{{ $item['sub'] }}</p>
                            <span class="mt-2 inline-block rounded-full bg-[#eff4fb] px-2 py-1 text-[11px] font-bold tabular-nums text-[#365987] dark:bg-[#25466b] dark:text-[#c0d6f0]">{{ $item['when'] }}</span>
                        </a>
                    </li>
                @empty
                    <li class="rounded-2xl border border-dashed border-slate-200 p-5 text-xs leading-7 text-slate-500 dark:border-[#365274] dark:text-slate-400">{{ $showcase['timeline_empty'] }}</li>
                @endforelse
            </ol>
        </section>

        <section class="learning-dashboard-panel flex min-w-0 flex-col p-6 sm:p-7" aria-label="{{ $showcase['actions_title'] }}">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[.1em] text-slate-500 dark:text-slate-400">{{ __('dashboard.feature_detail') }}</p>
                    <h3 class="mt-2 text-lg font-black leading-6 text-[#0d2d5b] dark:text-[#fce0a7]">{{ $showcase['actions_title'] }}</h3>
                </div>
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-[#fff6df] text-[#ac7822] dark:bg-[#4c3a1e] dark:text-[#ffd581]"><x-workspace-icon name="clipboard-check" class="h-5 w-5" /></span>
            </div>
            <div class="mt-6 flex flex-1 flex-col gap-3">
                @foreach($showcase['actions'] as $action)
                    <a href="{{ $action['url'] }}" class="group flex items-center gap-3 rounded-2xl border border-[#e6ecf3] bg-[#f5f7fa] p-4 transition hover:border-[#f5c45c] hover:bg-[#fff7e5] focus-visible:outline-2 focus-visible:outline-[#eab147] dark:border-[#294665] dark:bg-[#193658] dark:hover:border-[#f4c45c]">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-[#fff0c9] text-[#ba8021] dark:bg-[#4d3f27] dark:text-[#ffd37b]"><x-workspace-icon :name="$action['icon']" class="h-5 w-5" /></span>
                        <span class="min-w-0 flex-1 text-xs font-bold leading-5 text-[#203552] dark:text-[#e7effb]">{{ $action['label'] }}</span>
                        <span class="text-xl font-black tabular-nums text-[#0d2d5b] dark:text-white" title="{{ number_format($action['count']) }}">{{ \App\Support\DashboardNumber::compact($action['count']) }}</span>
                        <x-workspace-icon name="arrow-up-right" class="h-4 w-4 text-[#8a9fb9] transition group-hover:text-[#b57a20]" />
                    </a>
                @endforeach
            </div>
            <p class="mt-5 text-[11px] leading-6 text-slate-500 dark:text-slate-400">{{ __('dashboard.review_note') }}</p>
        </section>
    </div>
</section>
