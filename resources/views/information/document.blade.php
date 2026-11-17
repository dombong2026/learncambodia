@extends('layouts.public')
@section('canonical', e($document->publicUrl()))
@section('title', $document->seo_title ?: $document->title)
@section('meta_description', $document->seo_description ?: ($document->summary ?: \Illuminate\Support\Str::limit($document->body, 155)))
@if($document->featured_kind === 'image' && $document->featuredMedia)
    @section('og_image', route('editor-media.show', $document->featuredMedia))
@endif
@section('content')
<article class="mx-auto max-w-4xl px-4 py-12 sm:px-8 sm:py-16" data-public-document="{{ $document->type }}">
    @if($document->type === 'policy')
        <a href="{{ route('policies.index') }}" class="text-sm font-bold text-blue-700 dark:text-blue-300">Policy Center</a>
    @endif
    <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-5xl">{{ $document->title }}</h1>
    @if($document->summary)<p class="mt-4 text-base leading-7 text-slate-600 dark:text-slate-300">{{ $document->summary }}</p>@endif
    @if($document->type === 'policy' && ($document->policy_version || $document->policy_effective_on || $document->published_at))
        <dl class="mt-6 flex flex-wrap gap-x-8 gap-y-3 rounded-xl border border-slate-200 p-4 text-sm dark:border-slate-700">
            @if($document->policy_version)<div><dt class="font-semibold">Version</dt><dd>{{ $document->policy_version }}</dd></div>@endif
            @if($document->policy_effective_on)<div><dt class="font-semibold">Effective date</dt><dd><time datetime="{{ $document->policy_effective_on->toDateString() }}">{{ $document->policy_effective_on->toFormattedDateString() }}</time></dd></div>@endif
            @if($document->published_at)<div><dt class="font-semibold">Published</dt><dd><time datetime="{{ $document->published_at->toAtomString() }}">{{ $document->published_at->toFormattedDateString() }}</time></dd></div>@endif
        </dl>
    @endif
    <x-editorial-featured-media :entry="$document"/>
    <div class="rich-public-content mt-8 break-words text-base leading-8">
        @if($document->body_html)
            {!! \App\Support\EditorHtml::clean($document->body_html) !!}
        @else
            <p class="whitespace-pre-line">{{ $document->body }}</p>
        @endif
    </div>
    @if($document->type === 'policy')
        <div class="mt-8 flex flex-wrap gap-3 print:hidden">
            <button type="button" data-print-policy class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-bold dark:border-slate-600">Print policy</button>
            <button type="button" data-policy-link="{{ $document->publicUrl() }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-bold dark:border-slate-600">Copy policy link</button>
            <p data-policy-link-status role="status" class="self-center text-sm"></p>
        </div>
    @endif
</article>
@endsection
