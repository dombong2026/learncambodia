@props(['title','description'=>'','href','icon'=>'book-open','eyebrow'=>null,'meta'=>null,'action'=>'Explore'])
<a href="{{ $href }}" {{ $attributes->class(['premium-card group flex h-full min-w-0 flex-col rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-[#d4aa60] hover:shadow-xl dark:border-slate-700 dark:bg-[#19395b]']) }}>
    <span class="premium-corner-icon" aria-hidden="true"><x-workspace-icon :name="$icon" /></span>
    @if($eyebrow)<p class="premium-card-content mt-5 text-[11px] font-black uppercase tracking-[.12em] text-[#b0802a]">{{ $eyebrow }}</p>@endif
    <h3 class="premium-card-content mt-4 break-words text-lg font-black leading-7 text-[#12365e] group-hover:underline dark:text-white">{{ $title }}</h3>
    @if($description)<p class="premium-card-content mt-3 flex-1 break-words text-sm leading-8 text-slate-600 dark:text-slate-300">{{ $description }}</p>@else<div class="flex-1"></div>@endif
    <div class="premium-card-content mt-6 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-4 text-xs dark:border-slate-700">
        <span class="font-semibold text-slate-500 dark:text-slate-300">{{ $meta }}</span>
        <strong class="text-[#a77a2c] dark:text-[#f1c46d]">{{ $action }}</strong>
    </div>
</a>
