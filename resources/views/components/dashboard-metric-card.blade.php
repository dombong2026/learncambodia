@props(['stat'])
<a href="{{ $stat['url'] }}" class="premium-card group flex min-h-44 min-w-0 flex-col justify-between rounded-3xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-blue-700 sm:p-6" aria-label="{{ $stat['label'] }}: {{ number_format($stat['value']) }}. {{ $stat['detail'] }}">
            <span class="premium-corner-icon" aria-hidden="true"><x-workspace-icon :name="$stat['icon']" /></span>
            <div class="premium-card-content flex items-start justify-between gap-3">
                <p class="max-w-[165px] text-[11px] font-bold uppercase leading-5 tracking-[.06em] text-slate-500 dark:text-slate-400 sm:text-xs">{{ $stat['label'] }}</p>
            </div>
            <div class="premium-card-content mt-5">
                <p class="text-4xl font-extrabold tracking-tight tabular-nums text-slate-950 dark:text-white md:text-[2.8rem]" title="{{ number_format($stat['value']) }}">{{ \App\Support\DashboardNumber::compact($stat['value']) }}</p>
                <p class="mt-2 line-clamp-2 text-[11px] leading-5 text-slate-500 dark:text-slate-400">{{ $stat['detail'] }}</p>
            </div>
        </a>
