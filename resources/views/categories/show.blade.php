@extends('layouts.public')
@section('title', $category->name)
@section('meta_description', $category->description)
@section('content')
<section class="bg-gradient-to-br from-blue-950 to-slate-950 text-white">
    <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <a href="{{ route('categories.index',['group'=>$category->group]) }}" class="text-sm font-semibold text-blue-200 hover:text-white">← {{ \App\Models\Category::GROUPS[$category->group] ?? 'Categories' }}</a>
        <div class="mt-6 inline-flex rounded-full border border-white/25 px-3 py-1 text-xs font-bold">{{ $category->code }}</div>
        <h1 class="mt-4 max-w-4xl text-4xl font-black tracking-tight sm:text-5xl">{{ $category->name }}</h1>
        <p class="mt-5 max-w-3xl text-base leading-8 text-slate-200">{{ $category->description }}</p>
    </div>
</section>
<section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
    <h2 class="text-2xl font-extrabold">{{ \App\Support\Appearance::value('course','heading') }}</h2>
    <p class="mt-3 text-sm text-slate-500">{{ \App\Support\Appearance::value('course','intro') }}</p>
    @if($courses->count())
        <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($courses as $course)
                <article class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                    <h3 class="text-xl font-bold">{{ $course->title }}</h3>
                    <p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-400">{{ $course->summary }}</p>
                    <div class="mt-5 flex gap-2 text-xs text-blue-700 dark:text-blue-400"><span>{{ $course->cefr_level ?: 'All levels' }}</span><span>•</span><span>{{ ucfirst($course->delivery_mode) }}</span></div>
                </article>
            @endforeach
        </div>
        <div class="mt-8">{{ $courses->links() }}</div>
    @else
        <p class="mt-4 max-w-2xl text-sm leading-7 text-slate-600 dark:text-slate-400">No courses have been academically approved for publication in this pathway yet. The category information above remains available for orientation.</p>
    @endif
    @if($relatedCategories->isNotEmpty())
    <div class="mt-12"><h2 class="text-xl font-bold">Related pathways</h2><div class="mt-4 flex flex-wrap gap-3">
        @foreach($relatedCategories as $related)
            <a href="{{ route('categories.show',$related) }}" class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold hover:border-blue-400 dark:border-slate-700 dark:bg-slate-900">{{ $related->code }} · {{ $related->name }}</a>
        @endforeach
    </div></div>
    @endif
    <p class="mt-12 text-xs text-slate-500">{{ config('learning.external_assessment_disclaimer') }}</p>
</section>
@endsection
