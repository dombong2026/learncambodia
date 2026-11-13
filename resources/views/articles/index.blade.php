@extends('layouts.public')
@section('title', __('frontend.articles'))
@section('content')
<section class="bg-[#0c2d5b] text-white"><div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8"><p class="text-xs font-bold uppercase tracking-widest text-[#f6c465]">{{ __('frontend.study_resources') }}</p><h1 class="mt-3 text-4xl font-black">{{ __('frontend.articles') }}</h1><p class="mt-4 max-w-2xl text-sm leading-8 text-[#cadced]">{{ __('frontend.articles_description') }}</p></div></section>
<section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
    <form method="GET" action="{{ route('articles.index') }}" role="search" class="flex max-w-xl gap-2"><label class="sr-only" for="article-search">{{ __('frontend.search_articles') }}</label><input type="search" name="q" value="{{ $search }}" id="article-search" maxlength="80" placeholder="{{ __('frontend.search_articles') }}" class="min-w-0 flex-1 rounded-xl border border-slate-300 bg-white p-3 text-sm dark:border-slate-700 dark:bg-slate-900"><button type="submit" class="rounded-xl bg-[#0c2d5b] px-5 py-3 text-sm font-bold text-white">{{ __('frontend.apply') }}</button></form>
    <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($articles as $article)
            <a href="{{ route('articles.show',$article->slug) }}" class="group flex flex-col rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-[#dab05c] dark:border-slate-800 dark:bg-slate-900"><span class="text-xs font-bold uppercase tracking-widest text-[#b17a22]">{{ __('frontend.study_resources') }}</span><h2 class="mt-4 text-xl font-black leading-7 text-[#0c2d5b] group-hover:underline dark:text-white">{{ $article->title }}</h2><p class="mt-4 flex-1 text-sm leading-7 text-slate-600 dark:text-slate-400">{{ $article->summary }}</p><span class="mt-6 text-xs font-bold text-blue-700 dark:text-blue-400">{{ __('frontend.read_article') }} →</span></a>
        @empty
            <p class="col-span-full rounded-2xl border border-dashed border-slate-300 p-8 text-sm text-slate-500">{{ __('frontend.no_articles') }}</p>
        @endforelse
    </div>
    <div class="mt-8">{{ $articles->links() }}</div>
</section>
@endsection
