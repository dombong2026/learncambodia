@php
    $interactiveCampaigns = \App\Models\HomeCampaign::published()->latest('published_at')->limit(3)->get();
@endphp
@if($interactiveCampaigns->isNotEmpty())
<section class="border-y border-slate-200 bg-[#f8f5ee] py-12 dark:border-slate-800 dark:bg-[#0d2444]">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
            <div><p class="text-xs font-black uppercase tracking-widest text-[#a3762c]">Interactive learning</p>
                <h2 class="mt-1 text-2xl font-black text-[#0e315e] dark:text-white">Quiz & Exam campaigns</h2></div>
        </div>
        <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            @foreach($interactiveCampaigns as $campaign)
                <a href="{{ route('interactive-campaign.show', $campaign) }}" class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:border-amber-400 hover:shadow-lg dark:border-slate-700 dark:bg-[#183759]">
                    @if($campaign->cover_path)
                        <img src="{{ route('interactive-campaign.cover', $campaign) }}" alt="{{ $campaign->title }} cover" loading="lazy" class="aspect-video w-full object-cover">
                    @endif
                    <div class="p-5">
                        <span class="text-[11px] font-extrabold uppercase tracking-widest text-[#ac7929]">{{ $campaign->kind }}</span>
                        <h3 class="mt-2 text-base font-black text-[#0e315e] dark:text-white">{{ $campaign->title }}</h3>
                        @if($campaign->summary)<p class="mt-2 line-clamp-2 text-xs leading-6 text-slate-600 dark:text-slate-300">{{ $campaign->summary }}</p>@endif
                        <span class="mt-4 inline-block text-xs font-black text-[#9c702d]">Start interactive practice →</span>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif
