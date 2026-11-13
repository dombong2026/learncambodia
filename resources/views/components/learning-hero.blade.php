@props(['title','subtitle'=>'','eyebrow'=>'Learn Cambodia','icon'=>'book-open','stats'=>[],'actionUrl'=>null,'actionLabel'=>null])
<section class="relative isolate overflow-hidden bg-gradient-to-br from-[#092750] via-[#143e6d] to-[#0e3059] text-white">
    <div class="pointer-events-none absolute -right-20 -top-24 h-96 w-96 rounded-full border-[54px] border-[#f1bd62]/10" aria-hidden="true"></div>
    <div class="pointer-events-none absolute bottom-0 right-[15%] h-32 w-72 -rotate-12 bg-[#eab454]/10 blur-3xl" aria-hidden="true"></div>
    <div class="relative mx-auto grid max-w-[1600px] items-center gap-10 px-4 py-14 sm:px-8 lg:grid-cols-[minmax(0,1.3fr)_minmax(260px,.7fr)] lg:px-12 lg:py-20">
        <div class="min-w-0">
            <nav aria-label="Breadcrumb" class="mb-9 flex flex-wrap gap-2 text-xs text-[#d2e4f5]">
                <a href="{{ route('home') }}" class="hover:underline">Home</a><span aria-hidden="true">/</span><span aria-current="page" class="font-bold text-[#f0c775]">{{ $eyebrow }}</span>
            </nav>
            <p class="text-xs font-extrabold uppercase tracking-[.18em] text-[#f0c26e]">{{ $eyebrow }}</p>
            <h1 class="mt-4 max-w-5xl break-words text-4xl font-black leading-tight tracking-tight sm:text-5xl xl:text-6xl">{{ $title }}</h1>
            @if($subtitle)<p class="mt-5 max-w-3xl text-base leading-9 text-[#d9e6f3]">{{ $subtitle }}</p>@endif
            @if($actionUrl && $actionLabel)
                <a href="{{ $actionUrl }}" class="mt-7 inline-flex items-center gap-2 rounded-xl bg-[#f0bf64] px-6 py-3.5 text-sm font-black text-[#0c305a] transition hover:bg-[#ffda94]">
                    <x-workspace-icon name="arrow-up-right" class="h-4 w-4"/>{{ $actionLabel }}
                </a>
            @endif
        </div>
        <div class="grid min-w-0 grid-cols-2 gap-4">
            @foreach($stats as $stat)
                <div class="relative flex min-h-36 flex-col justify-end rounded-2xl border border-white/20 bg-white/10 p-5 backdrop-blur-sm">
                    <x-workspace-icon :name="$stat['icon'] ?? $icon" class="absolute right-4 top-4 h-6 w-6 text-[#f3c974]"/>
                    <strong class="text-3xl font-black tabular-nums sm:text-4xl">{{ $stat['value'] ?? '—' }}</strong>
                    <span class="mt-2 text-xs font-bold leading-5 text-[#d6e5f2]">{{ $stat['label'] ?? '' }}</span>
                </div>
            @endforeach
            @if(count($stats)===0)
                <div class="col-span-2 flex min-h-64 flex-col items-center justify-center rounded-3xl border border-white/15 bg-white/10 p-8 text-center">
                    <x-workspace-icon :name="$icon" class="h-24 w-24 text-[#f0bd62]"/>
                    <p class="mt-5 text-sm font-bold text-[#d8e6f4]">Approved learning content & secure student access</p>
                </div>
            @endif
        </div>
    </div>
</section>
