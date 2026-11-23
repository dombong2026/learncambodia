@php
    $campaigns = \App\Models\HomepageCampaignItem::query()
        ->orderByDesc('updated_at')
        ->orderByDesc('id')
        ->paginate(10, ['*'], 'campaign_page')
        ->withQueryString();
@endphp
<section class="mt-6 min-w-0 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-[#132d4a] sm:p-6" id="homepage-campaign-management">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-4">
        <div>
            <span class="text-[10px] font-extrabold uppercase tracking-widest text-amber-600 dark:text-amber-400">HOMEPAGE CAMPAIGNS</span>
            <h2 class="mt-1 text-lg font-black text-[#0e315e] dark:text-white">Quiz &amp; Exam manager</h2>
            <p class="mt-1 text-xs leading-6 text-slate-500 dark:text-slate-300">Manage individual campaigns shown in the homepage hero. 10 campaigns per page.</p>
        </div>
        <a href="{{ route('admin.appearance.campaign.edit', ['new' => 1, 'lang' => 'en']) }}"
            class="inline-flex items-center gap-2 rounded-xl bg-[#17487c] px-4 py-2.5 text-xs font-extrabold text-white hover:bg-[#2565a0]">
            <span aria-hidden="true">＋</span> Create a new campaign
        </a>
    </div>
    @if($errors->any())
        <div role="alert" class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-xs font-bold text-red-800 dark:border-red-700 dark:bg-red-950 dark:text-red-200">{{ $errors->first() }}</div>
    @endif
    @if(session('status'))
        <p class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-xs font-bold text-emerald-800" role="status">{{ session('status') }}</p>
    @endif
    <div class="w-full overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
        <table class="w-full min-w-[790px] border-collapse text-left text-xs">
            <thead class="bg-slate-50 text-[11px] font-extrabold uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                <tr>
                    <th scope="col" class="px-4 py-3">Campaign</th>
                    <th scope="col" class="px-4 py-3">Type</th>
                    <th scope="col" class="px-4 py-3">Lessons / Questions</th>
                    <th scope="col" class="px-4 py-3">Publication</th>
                    <th scope="col" class="px-4 py-3">Updated</th>
                    <th scope="col" class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse($campaigns as $campaign)
                    @php
                        $rows = $campaign->draft ?? $campaign->published ?? [];
                        $questions = collect($rows)->sum(fn($row) => count($row['questions'] ?? []));
                    @endphp
                    <tr class="align-middle text-slate-700 hover:bg-slate-50/70 dark:text-slate-200 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="h-12 w-[85px] shrink-0 overflow-hidden rounded-lg border border-slate-200 bg-slate-100 dark:border-slate-600 dark:bg-slate-800">
                                    @if($campaign->cover_asset)
                                        <img src="{{ route('campaign-manager.cover', $campaign) }}" alt="" loading="lazy" class="h-full w-full object-cover">
                                    @else
                                        <div class="grid h-full w-full place-items-center text-[10px] font-bold text-slate-400">No cover</div>
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <a class="block max-w-[270px] truncate font-bold text-[#164879] hover:underline dark:text-sky-300"
                                        href="{{ route('admin.appearance.campaign.edit', ['id'=>$campaign->id, 'lang'=>'en']) }}">{{ $campaign->title }}</a>
                                    <span class="mt-1 block text-[10px] text-slate-500 dark:text-slate-400">#{{ $campaign->id }} · v{{ $campaign->version }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3"><span class="rounded-lg border border-slate-200 px-2 py-1 font-bold uppercase dark:border-slate-700">{{ $campaign->kind }}</span></td>
                        <td class="px-4 py-3 font-semibold">{{ count($rows) }} / {{ $questions }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold {{ $campaign->status === 'published' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-300' }}">
                                <span aria-hidden="true">●</span> {{ ucfirst($campaign->status) }}
                            </span>
                            @if($campaign->draft !== null && $campaign->status === 'published')
                                <span class="mt-1 block text-[10px] text-amber-600">Draft changes saved</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-500 dark:text-slate-300">{{ $campaign->updated_at?->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-right">
                            <details class="relative inline-block text-left" data-campaign-menu>
                                <summary class="inline-grid h-9 w-9 cursor-pointer list-none place-items-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-blue-500 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-700"
                                    aria-label="Actions for {{ $campaign->title }}" title="Campaign actions">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
                                </summary>
                                <div data-campaign-popover class="fixed z-[200] min-w-40 rounded-xl border border-slate-200 bg-white p-1.5 text-left shadow-xl dark:border-slate-700 dark:bg-[#173956]">
                                    <a class="block rounded-lg px-3 py-2 font-bold hover:bg-slate-100 dark:hover:bg-slate-700"
                                        href="{{ route('admin.appearance.campaign.edit', ['id'=>$campaign->id, 'lang'=>'en']) }}">Edit</a>
                                    <form method="POST" action="{{ route('admin.appearance.campaign-manager.status', $campaign) }}">
                                        @csrf
                                        <button type="submit" name="action" value="draft" class="block w-full rounded-lg px-3 py-2 text-left font-bold hover:bg-slate-100 dark:hover:bg-slate-700"
                                            @disabled($campaign->status === 'draft')>Draft</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.appearance.campaign-manager.status', $campaign) }}">
                                        @csrf
                                        <button type="submit" name="action" value="publish" class="block w-full rounded-lg px-3 py-2 text-left font-bold hover:bg-slate-100 dark:hover:bg-slate-700"
                                            @disabled($campaign->status === 'published' && $campaign->draft === null)>Publish</button>
                                    </form>
                                </div>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-12 text-center text-sm text-slate-500">No campaigns created yet. Choose <strong>Create a new campaign</strong> to begin.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-5 flex flex-wrap items-center justify-between gap-4">
        <p class="text-xs text-slate-500 dark:text-slate-300">Showing {{ $campaigns->firstItem() ?? 0 }}–{{ $campaigns->lastItem() ?? 0 }} of {{ $campaigns->total() }} campaigns</p>
        <div class="min-w-0 flex-1 sm:flex-initial">{{ $campaigns->onEachSide(1)->links() }}</div>
    </div>
    <form method="GET" action="{{ route('admin.appearance.edit','home') }}" class="mt-4 flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 pt-4 dark:border-slate-700">
        <input type="hidden" name="lang" value="en">
        <label for="campaign-jump-page" class="text-xs font-bold text-slate-600 dark:text-slate-300">Go to page</label>
        <input id="campaign-jump-page" type="number" name="campaign_page" min="1" max="{{ max(1,$campaigns->lastPage()) }}"
            value="{{ $campaigns->currentPage() }}" class="w-20 rounded-lg border border-slate-300 bg-transparent px-3 py-2 text-xs dark:border-slate-700">
        <span class="text-xs text-slate-500">of {{ $campaigns->lastPage() }}</span>
        <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-bold hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">Go</button>
    </form>
</section>
<style>
#homepage-campaign-management details>summary::-webkit-details-marker{display:none}
#homepage-campaign-management [data-campaign-menu][open] summary{background:#e9f2fb}
#homepage-campaign-management table{border-spacing:0}
</style>

<script>
(function(){
  const root=document.getElementById('homepage-campaign-management');
  if(!root)return;
  const menus=[...root.querySelectorAll('[data-campaign-menu]')];
  function place(menu){
    const trigger=menu.querySelector('summary');
    const panel=menu.querySelector('[data-campaign-popover]');
    if(!trigger||!panel)return;
    const r=trigger.getBoundingClientRect(),width=180;
    panel.style.position='fixed';
    panel.style.right='auto';
    panel.style.left=Math.max(8,Math.min(window.innerWidth-width-8,r.right-width))+'px';
    panel.style.top=(r.bottom+6)+'px';
    panel.style.minWidth=width+'px';
  }
  menus.forEach(menu=>menu.addEventListener('toggle',()=>{
    if(!menu.open)return;
    menus.filter(other=>other!==menu).forEach(other=>other.open=false);
    place(menu);
  }));
  document.addEventListener('click',event=>{
    if(!root.contains(event.target)||!event.target.closest('[data-campaign-menu]')){
      menus.forEach(menu=>menu.open=false);
    }
  });
  window.addEventListener('scroll',()=>menus.forEach(menu=>{if(menu.open)menu.open=false}),true);
  window.addEventListener('resize',()=>menus.forEach(menu=>{if(menu.open)menu.open=false}));
})();
</script>
