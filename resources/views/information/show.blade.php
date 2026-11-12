@extends('layouts.public')
@section('title', $title)
@section('content')
<section class="mx-auto max-w-4xl px-4 py-16 sm:px-6 lg:px-8">
    <a href="{{ route('home') }}" class="text-sm font-semibold text-blue-700 hover:underline dark:text-blue-400">← Back to Learn Cambodia</a>
    <h1 class="mt-8 text-4xl font-black tracking-tight">{{ $title }}</h1>
    <p class="mt-5 text-xl leading-9 text-slate-600 dark:text-slate-300">{{ $lede }}</p>
    <div class="mt-8 rounded-3xl border border-slate-200 bg-white p-8 text-base leading-9 text-slate-700 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">{{ $body }}</div>
    <div class="mt-8 flex flex-wrap gap-4">
        <a class="rounded-full bg-blue-700 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-800" href="{{ route('categories.index') }}">Explore categories</a>
        <a class="rounded-full border border-slate-300 px-5 py-3 text-sm font-semibold hover:border-blue-600 dark:border-slate-700" href="{{ route('cefr.index') }}">Explore CEFR</a>
    </div>
    @if(in_array($page, ['terms','privacy'], true))
        <p class="mt-8 text-sm font-semibold text-amber-700 dark:text-amber-400">This document is a preliminary draft and requires legal review before launching the registration system publicly.</p>
    @endif
</section>
@endsection
