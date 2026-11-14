@extends('layouts.public')
@section('title','Help Center')
@section('content')
<section class="mx-auto max-w-7xl px-4 py-12 sm:px-6">
    <p class="text-xs font-bold uppercase tracking-wider text-amber-600">Learn Cambodia Support</p>
    <h1 class="mt-3 text-3xl font-black">Help Center</h1>
    <p class="mt-3 text-sm text-slate-500">Verified frequently asked questions and help articles.</p>
    <form method="GET" role="search" class="my-6 flex max-w-xl gap-2"><label for="help-search" class="sr-only">Search help</label><input id="help-search" type="search" name="q" value="{{ $query }}" maxlength="80" class="min-w-0 flex-1 rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-900" placeholder="Search help"><button class="rounded-xl bg-blue-700 px-5 py-3 font-bold text-white">Search</button></form>
    <h2 class="mb-5 text-xl font-black">Frequently Asked Questions</h2>
    <div class="grid gap-4 md:grid-cols-2">
        @forelse($faqs as $faq)<a class="rounded-2xl border border-slate-200 bg-white p-6 hover:border-amber-400 dark:border-slate-700 dark:bg-slate-900" href="{{ route('help.show',['kind'=>'faqs','entry'=>$faq->slug]) }}"><h3 class="font-bold">{{ $faq->title }}</h3><p class="mt-2 text-sm text-slate-500">{{ $faq->summary }}</p><span class="mt-3 inline-block text-xs font-bold text-blue-600">Read answer</span></a>
        @empty<p class="text-sm text-slate-500">No approved FAQs match your search.</p>@endforelse
    </div>
    <h2 class="mb-5 mt-10 text-xl font-black">Help Articles</h2>
    <div class="grid gap-4 md:grid-cols-2">
        @forelse($articles as $article)<a class="rounded-2xl border border-slate-200 bg-white p-6 hover:border-amber-400 dark:border-slate-700 dark:bg-slate-900" href="{{ route('help.show',['kind'=>'articles','entry'=>$article->slug]) }}"><h3 class="font-bold">{{ $article->title }}</h3><p class="mt-2 text-sm text-slate-500">{{ $article->summary }}</p><span class="mt-3 inline-block text-xs font-bold text-blue-600">Read article</span></a>
        @empty<p class="text-sm text-slate-500">No approved help articles match your search.</p>@endforelse
    </div>
    <div class="mt-7">{{ $articles->links() }}</div>
    <a href="{{ route('information.show','contact') }}" class="mt-8 inline-block rounded-xl bg-blue-700 px-6 py-3 text-sm font-bold text-white">Contact Support</a>
</section>
@endsection