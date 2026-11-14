@props(['title','subtitle'=>'','eyebrow'=>'Learn Cambodia','tone'=>'white','id'=>null,'actionUrl'=>null,'actionLabel'=>null])
@php
    $toneClasses=match($tone){
        'mist'=>'bg-[#f1f4f9] dark:bg-[#102b4e]',
        'navy'=>'bg-[#10345f] text-white',
        'gold'=>'bg-[#f9ecd4] dark:bg-[#3d3325]',
        default=>'bg-white dark:bg-[#0d2849]',
    };
@endphp
<section @if($id)id="{{ $id }}"@endif {{ $attributes->class(['scroll-mt-32 border-b border-slate-200/70 py-16 dark:border-slate-800',$toneClasses]) }}>
    <div class="mx-auto w-full max-w-[1600px] px-4 sm:px-8 lg:px-12">
        <div class="flex flex-wrap items-end justify-between gap-5">
            <div class="min-w-0">
                <p class="text-xs font-black uppercase tracking-[.16em] {{ $tone==='navy'?'text-[#f2c976]':'text-[#b4812c]' }}">{{ $eyebrow }}</p>
                <h2 class="mt-2 break-words text-2xl font-black leading-tight sm:text-3xl {{ $tone==='navy'?'text-white':'text-[#10345e] dark:text-white' }}">{{ $title }}</h2>
                @if($subtitle)<p class="mt-3 max-w-4xl text-sm leading-8 {{ $tone==='navy'?'text-[#d7e4f2]':'text-slate-600 dark:text-slate-300' }}">{{ $subtitle }}</p>@endif
            </div>
            @if($actionUrl && $actionLabel)<a href="{{ $actionUrl }}" class="rounded-xl border px-5 py-3 text-xs font-black transition hover:shadow-md {{ $tone==='navy'?'border-[#d8b875] text-[#f7d491]':'border-[#dbb76c] text-[#a7772a] dark:text-[#f4c978]' }}">{{ $actionLabel }}</a>@endif
        </div>
        <div class="mt-8 min-w-0">{{ $slot }}</div>
    </div>
</section>
