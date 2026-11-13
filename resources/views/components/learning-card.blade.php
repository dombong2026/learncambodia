@props(['title','description'=>'','href','icon'=>'book-open','eyebrow'=>null,'meta'=>null,'action'=>'Explore'])
<a href="{{ $href }}" {{ $attributes->class(['group flex h-full min-w-0 flex-col rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-[#d4aa60] hover:shadow-xl dark:border-slate-700 dark:bg-[#19395b]']) }}>
    <div class="flex items-start justify-between gap-4">
        <span class="grid h-13 w-13 shrink-0 place-items-center rounded-2xl bg-[#f6e8cb] p-3.5 text-[#a8792d] dark:bg-[#493e2c]"><x-workspace-icon :name="$icon" class="h-7 w-7"/></span>
        <x-workspace-icon name="arrow-up-right" class="h-5 w-5 text-[#b58b46]"/>
    </div>
    @if($eyebrow)<p class="mt-5 text-[11px] font-black uppercase tracking-[.12em] text-[#b0802a]">{{ $eyebrow }}</p>@endif
    <h3 class="mt-4 break-words text-lg font-black leading-7 text-[#12365e] group-hover:underline dark:text-white">{{ $title }}</h3>
    @if($description)<p class="mt-3 flex-1 break-words text-sm leading-8 text-slate-600 dark:text-slate-300">{{ $description }}</p>@else<div class="flex-1"></div>@endif
    <div class="mt-6 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-4 text-xs dark:border-slate-700">
        <span class="font-semibold text-slate-500 dark:text-slate-300">{{ $meta }}</span>
        <strong class="text-[#a77a2c] dark:text-[#f1c46d]">{{ $action }} →</strong>
    </div>
</a>
