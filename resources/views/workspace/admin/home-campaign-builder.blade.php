@extends('layouts.workspace')
@section('title', 'Homepage Campaign Builder')
@section('heading', 'Campaign Builder')
@section('description', 'Create rich, interactive Quiz and Exam campaigns for the homepage. Admins save drafts or publish directly.')
@section('content')
<style>
.campaign-field{display:block;font-size:.8rem;font-weight:700;color:inherit}
.campaign-field input,.campaign-field select,.campaign-field textarea{display:block;width:100%;margin-top:.4rem;padding:.65rem .85rem;border-radius:.75rem;border:1px solid #94a3b8;background:transparent;color:inherit;font-weight:400}
.campaign-primary{border-radius:.8rem;background:#0e315e;color:white;font-weight:800;padding:.7rem 1.2rem}
.campaign-secondary{border:1px solid #94a3b8;border-radius:.8rem;padding:.6rem .9rem;font-size:.8rem;font-weight:700}
.campaign-editor{min-height:105px;border:1px solid #94a3b8;border-radius:.7rem;padding:.85rem;font-size:.9rem;line-height:1.7;overflow:auto;outline-offset:2px}
.campaign-editor:empty:before{content:attr(data-placeholder);opacity:.55}
.campaign-editor[hidden],.campaign-source[hidden]{display:none}
.campaign-module{border:1px solid #cbd5e1;border-radius:1rem;padding:1rem;margin-top:1rem}
.campaign-lesson{border:1px solid #cbd5e1;border-radius:.9rem;padding:.9rem;margin-top:.75rem}
.campaign-step{display:grid;place-items:center;width:2.25rem;height:2.25rem;flex-shrink:0;border-radius:50%;background:#0e315e;color:#f7ce83;font-size:.8rem;font-weight:900}
.dark .campaign-module,.dark .campaign-lesson{border-color:#475569}
.campaign-toolbar{display:flex;gap:.35rem;flex-wrap:wrap;margin:.4rem 0}
.campaign-toolbar button{border:1px solid #94a3b8;border-radius:.4rem;padding:.25rem .55rem;font-size:.76rem}
.campaign-source{width:100%;min-height:100px;padding:.75rem;border:1px solid #94a3b8;border-radius:.75rem;background:transparent;font-family:monospace;font-size:.8rem}
</style>
<div class="mb-5 flex flex-wrap items-center gap-3">
    <a href="{{ route('admin.appearance.edit', 'home') }}" class="campaign-secondary">← Homepage appearance</a>
    <a href="{{ route('admin.campaign.builder') }}" class="campaign-secondary">+ New campaign</a>
    @if($campaign)
        <a href="{{ route('campaign.show', $campaign) }}" target="_blank" rel="noopener" class="campaign-secondary">Preview / View campaign</a>
        <span class="rounded-full bg-amber-100 px-3 py-1.5 text-xs font-bold text-amber-900 dark:bg-amber-900 dark:text-amber-100">{{ strtoupper($campaign->status) }}</span>
    @endif
</div>
@if($errors->any())
    <div role="alert" class="mb-5 rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-900">
        <strong>Unable to save.</strong> {{ $errors->first() }}
    </div>
@endif
<form id="home-campaign-form" enctype="multipart/form-data" method="POST" action="{{ route('admin.campaign.save') }}" class="grid min-w-0 items-start gap-5 xl:grid-cols-[minmax(0,1fr)_330px]">
    @csrf
    @if($campaign)<input type="hidden" name="campaign_id" value="{{ $campaign->id }}">@endif
    <input type="hidden" name="modules_json" id="campaign-modules-json" value="">
    <input type="hidden" name="publication_intent" id="campaign-publication-intent" value="draft">
    <div class="min-w-0 space-y-5">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 class="font-black">Campaign details</h2>
                <span class="text-xs font-semibold text-slate-500">Title · Cover · Modules · Lessons</span>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <label class="campaign-field">Campaign title
                    <input name="title" required maxlength="180" value="{{ old('title', $campaign?->title ?? 'English Grammar in Context') }}">
                </label>
                <label class="campaign-field">Campaign type
                    <select name="kind"><option value="quiz" @selected(old('kind', $campaign?->kind ?? 'quiz') === 'quiz')>Quiz</option><option value="exam" @selected(old('kind', $campaign?->kind ?? '') === 'exam')>Exam</option></select>
                </label>
            </div>
            <label class="campaign-field mt-4">Short description
                <textarea name="summary" rows="2" maxlength="600" placeholder="What learners will practice and learn">{{ old('summary', $campaign?->summary) }}</textarea>
            </label>
            <p class="mt-3 text-xs text-slate-500">Use one focused question or one media element per lesson. Each answer can return a specific explanation and feedback.</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div><h2 class="font-black">Learning timeline</h2><p class="text-xs text-slate-500">Drag modules or lessons, or use the accessible Move controls. Numbers update automatically.</p></div>
                <button type="button" id="campaign-add-module" class="campaign-primary">+ Add module</button>
            </div>
            <div id="campaign-module-list" class="mt-4" aria-live="polite"></div>
        </div>
    </div>
    <aside class="min-w-0 space-y-4 xl:sticky xl:top-24">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h2 class="font-black">Quiz / Exam cover media</h2>
            <p class="mt-1 text-xs leading-5 text-slate-500">Image on the right sidebar and on the public player. Recommended 1920 × 1080 px (16:9).</p>
            <div class="mt-4 aspect-video overflow-hidden rounded-xl border border-slate-200 bg-slate-100 dark:border-slate-700 dark:bg-slate-800">
                <img id="campaign-cover-preview" class="h-full w-full object-cover" alt="Campaign cover preview"
                    @if($campaign?->cover_path) src="{{ route('campaign.cover', $campaign) }}" @else hidden @endif>
                <div id="campaign-cover-empty" class="grid h-full place-items-center px-5 text-center text-xs text-slate-500" @if($campaign?->cover_path) hidden @endif>Choose a cover to preview the right-side media</div>
            </div>
            <label class="campaign-field mt-4">Upload cover image
                <input id="campaign-cover-input" name="cover" type="file" accept="image/png,image/jpeg,image/webp">
            </label>
        </section>
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h2 class="font-black">Publication</h2>
            <p class="mt-2 text-xs leading-6 text-slate-500">Admin-created campaigns do not enter an approval queue. Save Draft keeps the campaign private; Publish makes it available on the homepage and public player.</p>
            <div class="mt-4 flex flex-col gap-2">
                <button type="submit" data-campaign-intent="publish" class="campaign-primary">Publish campaign</button>
                <button type="submit" data-campaign-intent="draft" class="campaign-secondary">Save draft</button>
            </div>
            @if($campaign?->status === 'published')
                <p class="mt-3 text-xs text-amber-700 dark:text-amber-300">Saving this published campaign as Draft withdraws it from the public homepage.</p>
            @endif
        </section>
        @if($campaigns->isNotEmpty())
        <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900">
            <h2 class="font-black">Existing campaigns</h2>
            <div class="mt-3 space-y-2">
                @foreach($campaigns as $item)
                    <a href="{{ route('admin.campaign.builder', ['id' => $item->id]) }}" class="block rounded-xl border border-slate-200 px-3 py-2 text-sm hover:border-amber-500 dark:border-slate-700">
                        <span class="font-bold">{{ $item->title }}</span>
                        <span class="ml-1 text-xs text-slate-500">({{ $item->status }})</span>
                    </a>
                @endforeach
            </div>
        </section>
        @endif
    </aside>
</form>
<script id="campaign-initial-modules" type="application/json">@json($campaign?->modules ?? [])</script>
@endsection
