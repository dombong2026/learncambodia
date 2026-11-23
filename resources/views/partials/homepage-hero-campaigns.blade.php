@php
    $heroCampaigns = \App\Models\HomepageCampaignItem::published()
        ->orderByDesc('published_at')
        ->limit(8)
        ->get(['id','slug','title','kind','cover_asset','published_at']);
@endphp
@if($heroCampaigns->isNotEmpty())
<div id="hc-hero" class="relative z-10 mx-auto -mt-3 flex w-full max-w-7xl justify-end px-4 pb-8 sm:px-6 lg:px-8">
    <aside class="hc-panel w-full max-w-[470px] overflow-hidden rounded-2xl border border-white/20 bg-[#112f54]/95 p-4 text-white shadow-xl backdrop-blur-md sm:p-5" aria-label="Interactive homepage Quiz and Exam campaigns">
        <div id="hc-chooser">
            <div class="mb-4 flex items-center justify-between gap-2">
                <div><p class="text-[10px] font-bold uppercase tracking-[.14em] text-amber-300">START LEARNING</p>
                    <h2 class="mt-1 text-base font-black text-white">Quiz &amp; Exam practice</h2></div>
                <span class="rounded-full border border-amber-300/60 px-2.5 py-1 text-[10px] font-extrabold text-amber-200">{{ $heroCampaigns->count() }} available</span>
            </div>
            <div class="grid gap-2" id="hc-campaign-list">
                @foreach($heroCampaigns as $item)
                <button type="button" data-hc-start
                    data-play-url="{{ route('campaign-manager.play', $item) }}"
                    data-check-url="{{ route('campaign-manager.check', $item) }}"
                    class="hc-campaign flex min-w-0 items-center gap-3 rounded-xl border border-white/15 bg-white/5 p-2 text-left transition hover:border-amber-300/60 hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-300">
                    <span class="h-[54px] w-[96px] shrink-0 overflow-hidden rounded-lg bg-[#0b2544]">
                        @if($item->cover_asset)
                            <img src="{{ route('campaign-manager.cover',$item) }}" alt="" class="h-full w-full object-cover" loading="lazy">
                        @else
                            <span class="grid h-full w-full place-items-center text-xs text-amber-200">{{ strtoupper($item->kind) }}</span>
                        @endif
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-[13px] font-extrabold text-white">{{ $item->title }}</span>
                        <span class="mt-1 block text-[11px] font-semibold text-sky-200">{{ strtoupper($item->kind) }} · Start now</span>
                    </span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                </button>
                @endforeach
            </div>
        </div>
        <div id="hc-play" hidden>
            <div class="mb-3 flex items-center justify-between gap-3">
                <button type="button" id="hc-back" class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-bold text-sky-200 hover:bg-white/10">← All campaigns</button>
                <span id="hc-progress" class="text-[11px] font-bold text-amber-200"></span>
            </div>
            <h3 id="hc-title" class="mb-1 text-base font-black text-white"></h3>
            <p id="hc-lesson" class="mb-4 text-xs text-sky-200"></p>
            <div id="hc-question" aria-live="polite"></div>
            <div id="hc-feedback" class="mt-3 rounded-lg border border-white/15 bg-white/5 p-3 text-xs leading-6" role="status" aria-live="polite" hidden></div>
            <div class="mt-4 flex items-center justify-between gap-2">
                <button id="hc-next" type="button" class="rounded-lg bg-amber-300 px-4 py-2 text-xs font-black text-[#14365e]" hidden>Next question</button>
                <button id="hc-finish" type="button" class="rounded-lg border border-white/30 px-4 py-2 text-xs font-bold text-white" hidden>All campaigns</button>
            </div>
        </div>
        <div id="hc-error" role="alert" class="mt-3 rounded-lg border border-red-300/50 bg-red-900/40 p-3 text-xs" hidden></div>
    </aside>
</div>
<style>
#hc-hero [hidden]{display:none!important}
#hc-hero .hc-campaign{width:100%;cursor:pointer}
#hc-hero .hc-question-option{display:flex;align-items:flex-start;gap:.65rem;border:1px solid rgba(255,255,255,.2);padding:.7rem;border-radius:.65rem;margin:.5rem 0;font-size:12px;cursor:pointer}
#hc-hero .hc-question-option:has(input:checked){border-color:#fcd34d;background:rgba(252,211,77,.12)}
#hc-hero .hc-question-option input{accent-color:#fbbf24;margin-top:.2rem}
#hc-hero .hc-prompt{font-size:13px;font-weight:800;line-height:1.6;margin:.5rem 0 1rem}
#hc-hero .hc-instruction{font-size:12px;line-height:1.7;color:#cbdcf1}
#hc-hero .hc-instruction :is(p,ul,ol,blockquote){margin:.3rem 0}
#hc-hero .hc-instruction :is(ul,ol){padding-left:1.5rem}
#hc-hero .hc-submit{border-radius:.65rem;background:#fcd34d;color:#17365d;font-size:12px;font-weight:850;padding:.65rem 1.1rem}
#hc-hero .hc-submit:disabled{opacity:.55;cursor:not-allowed}
@media(max-width:850px){#hc-hero{margin-top:0}}
</style>
<script defer src="{{ asset('js/homepage-hero-campaigns.js') }}"></script>
@endif
