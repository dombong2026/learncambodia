@props([
    'title',
    'description' => null,
    'icon' => 'book-open',
    'href' => null,
    'action' => null,
])
<div {{ $attributes->class(['col-span-full flex flex-wrap items-center gap-4 rounded-2xl border border-dashed border-slate-300 bg-white/80 p-5 text-slate-600 dark:border-slate-700 dark:bg-[#153352] dark:text-slate-300']) }}>
    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-slate-100 text-[#456d9b] dark:bg-[#224367] dark:text-[#a9cbef]" aria-hidden="true">
        <x-workspace-icon :name="$icon" class="h-6 w-6" />
    </span>
    <div class="min-w-0 flex-1">
        <p class="text-sm font-bold text-[#12345e] dark:text-white">{{ $title }}</p>
        @if($description)
            <p class="mt-1 text-xs leading-6">{{ $description }}</p>
        @endif
    </div>
    @if($href && $action)
        <a href="{{ $href }}" class="rounded-lg border border-[#d2b077] px-4 py-2 text-xs font-bold text-[#8d5e1e] transition hover:bg-[#fff3dc] dark:border-[#826437] dark:text-[#f6ce88] dark:hover:bg-[#254669]">{{ $action }}</a>
    @endif
</div>
