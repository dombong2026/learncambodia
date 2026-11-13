@extends('layouts.workspace')
@section('title','Editorial Reviews')
@section('heading','Editorial Reviews')
@section('description','Independently review articles before they become publicly visible. Authors and submitting editors cannot approve their own work.')
@section('content')
<div class="mb-6 flex justify-end"><a href="{{ route('admin.editorial.index',['kind'=>'posts']) }}" class="rounded-full border border-slate-300 px-5 py-2.5 text-sm font-bold dark:border-slate-700">Back to article manager</a></div>
<div class="space-y-5">
    @forelse($entries as $entry)
        <section class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-wrap items-start justify-between gap-3"><div>
                <p class="text-xs font-bold uppercase tracking-widest text-blue-700 dark:text-blue-400">Article #{{ $entry->id }} · Pending review</p>
                <h2 class="mt-2 text-xl font-black">{{ $entry->title }}</h2>
                <p class="mt-2 text-xs text-slate-500">Author: {{ $entry->creator?->name }} · Last revision: {{ $entry->updated_at?->format('Y-m-d H:i') }}</p>
            </div></div>
            <p class="mt-5 font-semibold text-slate-700 dark:text-slate-300">{{ $entry->summary }}</p>
            <div class="mt-4 max-h-64 overflow-y-auto whitespace-pre-line break-words rounded-2xl bg-slate-50 p-5 text-sm leading-7 text-slate-600 dark:bg-slate-950 dark:text-slate-300">{{ $entry->body }}</div>
            @if(in_array(auth()->id(),[$entry->created_by,$entry->updated_by],true))
                <p class="mt-5 text-xs font-bold text-amber-700 dark:text-amber-400">Independent review required from a different administrator.</p>
            @else
                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    <form method="POST" action="{{ route('admin.editorial.review',['kind'=>'posts','entry'=>$entry]) }}">@csrf
                        <input type="hidden" name="decision" value="approve">
                        <button type="submit" class="w-full rounded-xl bg-emerald-700 px-5 py-3 text-sm font-bold text-white hover:bg-emerald-800">Approve &amp; publish article</button>
                    </form>
                    <form method="POST" action="{{ route('admin.editorial.review',['kind'=>'posts','entry'=>$entry]) }}" class="space-y-2">@csrf
                        <input type="hidden" name="decision" value="reject">
                        <label for="editorial-note-{{ $entry->id }}" class="block text-xs font-bold">Reason for rejection</label>
                        <textarea id="editorial-note-{{ $entry->id }}" name="note" rows="2" required maxlength="500" class="w-full rounded-xl border border-slate-300 bg-white p-3 text-sm dark:border-slate-700 dark:bg-slate-950"></textarea>
                        <div class="flex justify-end"><button type="submit" class="rounded-xl border border-red-300 px-5 py-2.5 text-sm font-bold text-red-700 dark:text-red-300">Reject for revision</button></div>
                    </form>
                </div>
            @endif
        </section>
    @empty
        <p class="rounded-3xl border border-dashed border-slate-300 p-8 text-sm text-slate-500 dark:border-slate-700">No articles awaiting editorial review.</p>
    @endforelse
</div>
<div class="mt-6">{{ $entries->links() }}</div>
@endsection
