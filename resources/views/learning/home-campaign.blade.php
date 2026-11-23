@extends('layouts.public')
@section('title', $campaign->title)
@section('meta_description', $campaign->summary ?: 'Learn and practice with this interactive English quiz or exam.')
@push('public_styles')
<style>
.campaign-player-step{display:grid;place-items:center;width:2.25rem;height:2.25rem;flex-shrink:0;border-radius:50%;background:#102f5d;color:#ffdc96;font-size:.84rem;font-weight:900}
.campaign-drop{border:1px dashed #94a3b8;border-radius:.75rem;padding:.7rem;min-height:3.25rem}
.campaign-chip{border:1px solid #94a3b8;border-radius:.65rem;padding:.6rem .8rem;cursor:grab;touch-action:manipulation}
.campaign-chip:focus-visible,.campaign-drop:focus-within{outline:2px solid #d5a953;outline-offset:2px}
.campaign-rich :is(p,div,blockquote,pre,ul,ol,table){margin:.5rem 0}
.campaign-rich :is(ul,ol){padding-left:1.75rem}
.campaign-rich pre{white-space:pre-wrap;overflow-wrap:anywhere;border-radius:.75rem;background:#0f172a;color:#f8fafc;padding:.85rem}
.campaign-rich table{border-collapse:collapse;width:100%}
.campaign-rich td,.campaign-rich th{border:1px solid #94a3b8;padding:.5rem}
</style>
@endpush
@section('content')
<section class="border-b border-slate-200 bg-gradient-to-br from-[#0d2b53] to-[#0e315e] py-10 text-white">
    <div class="mx-auto grid max-w-7xl items-center gap-7 px-4 sm:px-6 lg:grid-cols-[1fr_.8fr] lg:px-8">
        <div>
            <span class="text-xs font-black uppercase tracking-widest text-amber-300">{{ strtoupper($campaign->kind) }} · English practice</span>
            <h1 class="mt-3 text-3xl font-black leading-tight sm:text-5xl">{{ $campaign->title }}</h1>
            @if($campaign->summary)<p class="mt-4 max-w-2xl text-sm leading-7 text-slate-200">{{ $campaign->summary }}</p>@endif
            <p class="mt-4 text-xs text-slate-200">{{ count($campaign->modules ?? []) }} modules · {{ collect($campaign->modules ?? [])->sum(fn($m) => count($m['lessons'] ?? [])) }} lessons · Check answers with real feedback</p>
        </div>
        @if($campaign->cover_path)
        <img src="{{ route('campaign.cover', $campaign) }}" alt="{{ $campaign->title }} cover" class="aspect-video w-full rounded-2xl border border-white/20 object-cover shadow-xl">
        @endif
    </div>
</section>
<div class="mx-auto max-w-5xl px-4 py-9 sm:px-6">
    @if($campaign->status !== 'published')
        <p class="mb-5 rounded-xl bg-amber-100 p-4 text-sm font-bold text-amber-900">Admin preview. This campaign is not public.</p>
    @endif
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-black text-[#0e315e] dark:text-white">Learning timeline</h2>
        <a href="{{ route('home') }}" class="text-sm font-bold text-[#9b6d24]">← Homepage</a>
    </div>
    <div class="space-y-5">
    @foreach($campaign->modules ?? [] as $moduleIndex => $module)
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-6">
            <div class="mb-4 flex items-center gap-3">
                <span class="campaign-player-step">{{ $moduleIndex+1 }}</span>
                <h3 class="text-lg font-black">{{ $module['title'] }}</h3>
                <span class="ml-auto text-xs text-slate-500">{{ count($module['lessons'] ?? []) }} lessons</span>
            </div>
            <div class="ml-3 space-y-3 border-l-2 border-amber-300 pl-4 sm:ml-4 sm:pl-6">
            @foreach($module['lessons'] ?? [] as $lessonIndex => $lesson)
                <details class="rounded-xl border border-slate-200 p-4 dark:border-slate-700" @if($loop->first) open @endif>
                    <summary class="flex cursor-pointer list-none items-center gap-3">
                        <span class="campaign-player-step">{{ $moduleIndex+1 }}.{{ $lessonIndex+1 }}</span>
                        <span class="min-w-0 flex-1 text-sm font-bold">{{ $lesson['title'] }}</span>
                        <span class="text-xs text-slate-500">{{ str_replace('_',' ',$lesson['type']) }}</span>
                    </summary>
                    <div class="mt-4 text-sm leading-7">
                        @if($lesson['content_html'])
                            <div class="campaign-rich">{!! $lesson['content_html'] !!}</div>
                        @endif
                        @if(($lesson['media_kind'] ?? '') === 'image' && ($lesson['media_url'] ?? ''))
                            <img src="{{ $lesson['media_url'] }}" alt="Lesson media" loading="lazy" class="mt-4 max-h-80 w-full rounded-xl object-contain">
                        @elseif(($lesson['media_kind'] ?? '') === 'video' && ($lesson['media_url'] ?? ''))
                            <video controls preload="none" playsinline src="{{ $lesson['media_url'] }}" class="mt-4 w-full rounded-xl"></video>
                        @elseif(($lesson['media_kind'] ?? '') === 'audio' && ($lesson['media_url'] ?? ''))
                            <audio controls preload="none" src="{{ $lesson['media_url'] }}" class="mt-4 w-full"></audio>
                        @elseif(($lesson['media_kind'] ?? '') === 'youtube' && ($lesson['media_url'] ?? ''))
                            <a href="{{ $lesson['media_url'] }}" target="_blank" rel="noopener noreferrer" class="font-bold text-blue-600 underline dark:text-blue-300">Open the YouTube lesson</a>
                        @endif
                        @if($lesson['type'] !== 'reading')
                        <form action="{{ route('campaign.check', [$campaign, $lesson['id']]) }}" method="POST" data-campaign-check class="mt-5 space-y-4">
                            @csrf
                            @if($lesson['type'] === 'multiple_choice')
                                <fieldset class="space-y-2"><legend class="mb-2 font-bold">Choose the best answer</legend>
                                @foreach($lesson['options'] as $optionIndex => $option)
                                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 hover:border-amber-400 dark:border-slate-700">
                                        <input type="radio" class="mt-1" name="answer" value="{{ $optionIndex }}" required>
                                        <span>{{ $option }}</span>
                                    </label>
                                @endforeach
                                </fieldset>
                            @elseif($lesson['type'] === 'matching')
                                @php $choices = collect($lesson['pairs'])->pluck('right')->shuffle()->values(); @endphp
                                <p class="font-bold">Match each prompt to the correct answer. Drag a chip to a row, or select from the menu.</p>
                                <div class="mt-3 flex flex-wrap gap-2" data-campaign-chips>
                                    @foreach($choices as $choice)
                                        <button type="button" draggable="true" data-choice="{{ $choice }}" class="campaign-chip text-xs" title="Drag onto a matching row">{{ $choice }}</button>
                                    @endforeach
                                </div>
                                @foreach($lesson['pairs'] as $pairIndex => $pair)
                                <label data-match-target class="campaign-drop mt-3 grid items-center gap-3 sm:grid-cols-2">
                                    <span class="font-semibold">{{ $pair['left'] }}</span>
                                    <select name="answer[]" data-match-select required class="w-full rounded-lg border border-slate-300 bg-white p-2 text-sm text-slate-900">
                                        <option value="">Choose match…</option>
                                        @foreach($choices as $choice)<option value="{{ $choice }}">{{ $choice }}</option>@endforeach
                                    </select>
                                </label>
                                @endforeach
                            @elseif($lesson['type'] === 'ordering')
                                <p class="font-bold">Reorganize these blocks into the correct order. Drag or use the arrow buttons.</p>
                                <ol data-order-list class="mt-3 space-y-2">
                                    @foreach(collect($lesson['sequence'])->shuffle() as $step)
                                    <li draggable="true" data-value="{{ $step }}" class="flex items-center gap-2 rounded-xl border border-slate-300 p-3 dark:border-slate-700">
                                        <span class="cursor-grab text-slate-500" aria-hidden="true">⠿</span><span class="min-w-0 flex-1">{{ $step }}</span>
                                        <button type="button" data-order-move="-1" class="rounded border px-2" aria-label="Move block up">↑</button>
                                        <button type="button" data-order-move="1" class="rounded border px-2" aria-label="Move block down">↓</button>
                                    </li>
                                    @endforeach
                                </ol>
                            @endif
                            <button type="submit" class="rounded-xl bg-[#0e315e] px-5 py-2.5 text-sm font-black text-white hover:bg-[#1d4e82]">Check answer</button>
                            <div data-campaign-result class="hidden rounded-xl border border-slate-200 p-4 text-sm dark:border-slate-700" role="status" aria-live="polite">
                                <p data-result-feedback class="font-bold"></p>
                                <div data-result-explanation class="campaign-rich mt-3"></div>
                                <p data-result-correct class="mt-2 text-xs"></p>
                            </div>
                        </form>
                        @endif
                    </div>
                </details>
            @endforeach
            </div>
        </section>
    @endforeach
    </div>
</div>
<script>
document.querySelectorAll('[data-campaign-check]').forEach(form => {
    const answerArea=form.querySelector('[data-campaign-chips]');
    let dragging=null;
    answerArea?.addEventListener('dragstart',event=>{
        const chip=event.target.closest('[data-choice]'); if (!chip) return;
        dragging=chip.dataset.choice;event.dataTransfer.setData('text/plain',dragging);event.dataTransfer.effectAllowed='copy';
    });
    form.querySelectorAll('[data-match-target]').forEach(slot=>{
        slot.addEventListener('dragover',event=>event.preventDefault());
        slot.addEventListener('drop',event=>{
            event.preventDefault();
            const value=event.dataTransfer.getData('text/plain');
            if (Array.from(slot.querySelector('select').options).some(option=>option.value===value)) slot.querySelector('select').value=value;
        });
    });
    const list=form.querySelector('[data-order-list]');
    let moved=null;
    list?.addEventListener('dragstart',event=>{
        moved=event.target.closest('li[data-value]');
        if (!moved) return;
        event.dataTransfer.effectAllowed='move';event.dataTransfer.setData('text/plain','order');
    });
    list?.addEventListener('dragover',event=>event.preventDefault());
    list?.addEventListener('drop',event=>{
        event.preventDefault();const target=event.target.closest('li[data-value]');
        if(moved && target && moved!==target){list.insertBefore(moved,target); moved=null;}
    });
    list?.addEventListener('click',event=>{
        const btn=event.target.closest('[data-order-move]'); if(!btn)return;
        const row=btn.closest('li');
        const sibling=Number(btn.dataset.orderMove)<0?row.previousElementSibling:row.nextElementSibling;
        if(!sibling)return;
        if(Number(btn.dataset.orderMove)<0)list.insertBefore(row,sibling);else list.insertBefore(sibling,row);
        btn.focus();
    });
    form.addEventListener('submit',async event=>{
        event.preventDefault();
        let answer;
        if(list)answer=Array.from(list.querySelectorAll('li[data-value]')).map(row=>row.dataset.value);
        else if(answerArea)answer=Array.from(form.querySelectorAll('[data-match-select]')).map(select=>select.value);
        else answer=Number(form.querySelector('input[name="answer"]:checked')?.value);
        if(answerArea && answer.some(item=>!item))return;
        if(!list && !answerArea && !form.querySelector('input[name="answer"]:checked'))return;
        const submit=form.querySelector('button[type="submit"]');
        submit.disabled=true;submit.textContent='Checking…';
        const box=form.querySelector('[data-campaign-result]');
        try {
            const response=await fetch(form.action,{
                method:'POST',credentials:'same-origin',
                headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':form.querySelector('input[name="_token"]').value},
                body:JSON.stringify({answer})
            });
            if(!response.ok)throw new Error('The answer could not be checked. Please try again.');
            const result=await response.json();
            box.hidden=false;box.classList.remove('hidden');
            box.querySelector('[data-result-feedback]').textContent=(result.correct?'✓ ':'↻ ')+result.feedback;
            // Explanation HTML was sanitized with the server's EditorHtml allowlist before persistence.
            box.querySelector('[data-result-explanation]').innerHTML=result.explanation_html||'';
            box.querySelector('[data-result-correct]').textContent=result.correct?'Answer confirmed.':('Correct answer: '+(Array.isArray(result.expected)?result.expected.join(' → '):result.expected));
            box.querySelector('[data-result-feedback]').className=result.correct?'font-bold text-emerald-700 dark:text-emerald-300':'font-bold text-amber-700 dark:text-amber-300';
        }catch(error){
            box.hidden=false;box.classList.remove('hidden');
            box.querySelector('[data-result-feedback]').textContent=error.message;
        }finally{submit.disabled=false;submit.textContent='Check answer';}
    });
});
</script>
@endsection
