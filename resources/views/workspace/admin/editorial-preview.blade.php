@extends('layouts.workspace')
@section('title','Preview '.$entry->title)
@section('heading','Preview: '.$entry->title)
@section('description','Private editorial preview. This page is not a public publication.')
@section('heading_action')
<a href="{{ route('admin.editorial.edit',['kind'=>$kind,'entry'=>$entry]) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-bold dark:border-slate-700">Back to editor</a>
@endsection
@section('content')
<article class="mx-auto max-w-4xl space-y-4 rounded-xl border border-slate-200 p-6 dark:border-slate-700">
    <p class="text-xs font-bold uppercase tracking-wide text-amber-600">{{ ucfirst(str_replace('_',' ',$entry->type)) }} · {{ ucfirst($entry->status) }} · Private</p>
    <h2 class="text-3xl font-bold">{{ $entry->title }}</h2>
    <p class="text-xs text-slate-500">Search title: {{ $entry->seo_title ?: $entry->title }}</p>
    @if($entry->seo_description)<p class="text-xs text-slate-500">Search description: {{ $entry->seo_description }}</p>@endif
    @if($entry->summary)<p class="text-sm text-slate-500">{{ $entry->summary }}</p>@endif
    <div class="rich-public-content break-words text-sm leading-8">
        @if($entry->body_html){!! \App\Support\EditorHtml::clean($entry->body_html) !!}
        @else<span class="whitespace-pre-line">{{ $entry->body }}</span>@endif
    </div>
</article>
@endsection
