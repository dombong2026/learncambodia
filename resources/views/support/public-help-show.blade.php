@extends('layouts.public')
@section('title',$content->seo_title ?: $content->title)
@section('meta_description',$content->seo_description ?: $content->summary)
@section('content')
<article class="mx-auto max-w-4xl px-4 py-12 sm:px-6">
    <a href="{{ route('help.index') }}" class="text-sm font-bold text-blue-600">All Help Articles</a>
    <p class="mt-8 text-xs font-bold uppercase tracking-widest text-amber-600">{{ $content->type==='faq'?'FAQ':'Help Article' }}</p>
    <h1 class="mt-3 text-3xl font-black">{{ $content->title }}</h1>
    <p class="mt-4 text-slate-500">{{ $content->summary }}</p>
    <div class="rich-public-content mt-7 break-words rounded-3xl border border-slate-200 bg-white p-7 text-base leading-9 dark:border-slate-700 dark:bg-slate-900">@if($content->body_html){!! \App\Support\EditorHtml::clean($content->body_html) !!}@else<span class="whitespace-pre-line">{{ $content->body }}</span>@endif</div>
    <a href="{{ route('information.show','contact') }}" class="mt-8 inline-block rounded-xl bg-blue-700 px-6 py-3 text-sm font-bold text-white">Contact Support</a>
</article>
@endsection