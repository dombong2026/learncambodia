@extends('layouts.public')
@section('title', $article->title)
@section('meta_description', $article->summary)
@section('content')
<article class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
    <a href="{{ route('articles.index') }}" class="text-sm font-bold text-blue-700 hover:underline dark:text-blue-400">← {{ __('frontend.articles') }}</a>
    <h1 class="mt-6 text-4xl font-black leading-tight text-[#0c2d5b] dark:text-white">{{ $article->title }}</h1>
    <p class="mt-5 text-lg leading-8 text-slate-500">{{ $article->summary }}</p>
    <div class="mt-8 border-t border-slate-200 pt-7 text-base leading-9 text-slate-700 dark:border-slate-800 dark:text-slate-300">
        {{-- Plain text only: never render unsanitized editorial HTML. --}}
        <p class="whitespace-pre-line break-words">{{ $article->body }}</p>
    </div>
    <div class="mt-10 flex justify-end"><a href="{{ route('courses.index') }}" class="rounded-xl bg-[#0c2d5b] px-5 py-3 text-sm font-bold text-white">{{ __('frontend.courses_title') }}</a></div>
</article>
@endsection
