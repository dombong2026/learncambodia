@extends('layouts.public')
@section('canonical', e($article->publicUrl()))
@section('title',$article->seo_title ?: $article->title)
@section('meta_description', $article->seo_description ?: ($article->summary ?: \Illuminate\Support\Str::limit($article->body, 155)))
@section('content')
@php $a=\App\Support\Appearance::approved('single_blog'); @endphp
<x-detail-hero :title="$article->title" :summary="$article->summary" :entry="$article" icon="newspaper" :label="__('detail.article')">
    <x-slot:rail>
        <h2 class="text-xl font-black">{{ __('detail.start_reading') }}</h2>
        <a href="#article-body" class="mt-4 block rounded-xl bg-[#efbe62] px-5 py-3 text-center text-xs font-black text-[#0b2f5c] hover:bg-[#ffdc91]">{{ $a['reading_heading'] }}</a>
        <a href="{{ route('articles.index') }}" class="mt-3 block rounded-xl border border-[#d9b464] px-5 py-3 text-center text-xs font-bold text-white hover:bg-white/10">{{ __('detail.browse_articles') }}</a>
    </x-slot:rail>
</x-detail-hero>

<div class="detail-grid mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
    <div class="min-w-0 space-y-5">
        <article id="article-body" data-public-document="post" class="min-w-0 scroll-mt-40 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 dark:border-slate-700 dark:bg-[#152e50]">
            <h2 class="mb-5 text-xl font-black text-[#12355e] dark:text-white">{{ $a['reading_heading'] }}</h2>
            @if($article->body_html)
                <div class="rich-public-content break-words text-base leading-8 text-slate-700 dark:text-slate-200">{!! \App\Support\EditorHtml::clean($article->body_html) !!}</div>
            @else
                @forelse($paragraphs as $index=>$paragraph)
                    <section id="article-part-{{ $index+1 }}" class="scroll-mt-36 {{ $index>0?'border-t border-slate-200 pt-7 dark:border-slate-700':'' }} {{ $index>0?'mt-8':'' }}">
                        <p class="whitespace-pre-line break-words text-base leading-10 text-slate-700 dark:text-slate-200">{{ $paragraph }}</p>
                    </section>
                @empty
                    <p class="text-sm text-slate-500">No article body is available for reading.</p>
                @endforelse
            @endif
            <div class="mt-10 border-t border-slate-200 pt-6 text-xs leading-8 text-slate-500 dark:border-slate-700">Content on this page is for education and does not constitute an official examination result or qualification.</div>
        </article>
        <x-learning-detail-metadata :record="$article" kind="article" />
    </div>
    <aside class="min-w-0 space-y-5">
        @if(!$article->body_html && count($paragraphs) > 1)
            <div class="rounded-3xl border border-slate-200 bg-[#f5f7fa] p-6 dark:border-slate-700 dark:bg-[#19395a]">
                <h2 class="text-lg font-black text-[#10345d] dark:text-white">{{ $a['outline_heading'] }}</h2>
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-300">Jump between article paragraphs</p>
                <nav aria-label="Article paragraphs" class="mt-5 space-y-2">
                    @foreach($paragraphs as $index=>$paragraph)
                        <a href="#article-part-{{ $index+1 }}" class="block rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs font-semibold leading-6 text-[#12375f] hover:border-[#d1a259] dark:border-slate-700 dark:bg-[#102c50] dark:text-white">Part {{ $index+1 }} · {{ \Illuminate\Support\Str::limit(preg_replace('/\s+/u',' ',trim($paragraph)),48) }}</a>
                    @endforeach
                </nav>
            </div>
        @endif
        <div class="rounded-3xl border border-slate-200 bg-[#f8fafc] p-6 dark:border-slate-700 dark:bg-[#19395a]">
            <h2 class="text-lg font-black">Contributor information</h2>
            @if($author)
                <p class="mt-3 text-base font-bold">{{ $author->display_name }}</p>
                <p class="mt-2 text-sm leading-7 text-slate-500 dark:text-slate-300">{{ $author->headline }}</p>
                <a href="{{ route('authors.show',$author) }}" class="mt-4 inline-block text-xs font-black text-[#a67829]">Read approved author biography →</a>
            @else
                <p class="mt-3 text-sm leading-8 text-slate-500 dark:text-slate-300">Published by the editorial team. Individual contributor identity remains private unless the author consents and a reviewer approves the public profile.</p>
            @endif
        </div>
        <a href="{{ route('information.show','contact') }}" class="block rounded-2xl bg-[#113862] px-6 py-4 text-center text-xs font-bold text-white">Question about this article? Contact support</a>
    </aside>
</div>
<x-learning-section :title="$a['related_heading']" eyebrow="Continue reading" subtitle="Only other genuinely published articles appear below." tone="mist">
    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        @forelse($related as $entry)
            <x-learning-card :href="route('articles.show',$entry->getRouteKey())" :title="$entry->title" :description="$entry->summary" icon="newspaper" eyebrow="Educational article" action="Read article"/>
        @empty
            <p class="col-span-full rounded-2xl border border-dashed border-slate-300 p-9 text-sm text-slate-500">No other published articles yet. <a href="{{ route('articles.index') }}" class="font-bold underline">Browse the article directory</a>.</p>
        @endforelse
    </div>
</x-learning-section>
<x-learning-section :title="$a['engagement_heading']" eyebrow="Thoughtful learning" tone="white" subtitle="Public comments, invented likes, and external discussion groups are intentionally not enabled. Use verified support and actual learning resources.">
    <div class="grid gap-5 md:grid-cols-3">
        <x-learning-card :href="route('learning.reading')" title="Practice independent reading" description="Explore more reviewed texts and build a sustainable reading habit." icon="book-open"/>
        <x-learning-card :href="route('courses.index')" title="Apply what you read" description="Find an approved course with structured learning outcomes and optional practice." icon="graduation"/>
        <x-learning-card :href="route('information.show','contact')" title="Ask a question" description="Send a private inquiry to the authorized administrative review queue." icon="mail"/>
    </div>
</x-learning-section>
<x-learning-section :title="$a['cta_heading']" eyebrow="Learn Cambodia" tone="navy" :action-url="route('articles.index')" action-label="All educational articles" subtitle="Continue through real educational reading and approved learning opportunities.">
    <div class="grid gap-5 sm:grid-cols-2">
        <x-learning-card :href="route('learning.library')" title="Library & study resources" description="Browse approved published materials." icon="library"/>
        <x-learning-card :href="route('learning.recommendations')" title="Learning pathways" description="Signed-in students can see relevant courses based on their own learning history." icon="compass"/>
    </div>
</x-learning-section>
@endsection
