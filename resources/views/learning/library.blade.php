@extends('layouts.public')
@section('title',\App\Support\Appearance::value('library','hero_heading'))
@section('content')
@php $a=\App\Support\Appearance::approved('library'); @endphp
<x-learning-hero :title="$a['hero_heading']" :subtitle="$a['hero_intro']" eyebrow="Digital library / Reviewed materials" icon="library"
    :action-url="'#library-resources'" action-label="Browse available materials"
    :stats="[
        ['value'=>number_format($articles->total()),'label'=>'Matching published articles','icon'=>'newspaper'],
        ['value'=>number_format($courses->count()),'label'=>'Featured approved courses','icon'=>'book-open'],
    ]"/>
<x-learning-section id="library-resources" :title="$a['resources_heading']" eyebrow="Digital reading collection" subtitle="Only reviewed, publicly available articles are listed. Download buttons are intentionally absent without actual authorized files." tone="white">
    <form role="search" method="GET" action="{{ route('learning.library') }}#library-resources" class="flex max-w-3xl flex-wrap gap-2">
        <label for="library-q" class="sr-only">Search published library materials</label>
        <input id="library-q" type="search" name="q" value="{{ $search }}" maxlength="80" placeholder="Search published titles and descriptions" class="min-w-[200px] flex-1 rounded-xl border border-slate-300 bg-white px-5 py-3.5 text-sm dark:border-slate-700 dark:bg-[#193654]">
        <button type="submit" class="rounded-xl bg-[#133764] px-6 py-3.5 text-xs font-black text-white">Search</button>
    </form>
    <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @forelse($articles as $article)
            <x-learning-card :href="route('articles.show',$article->slug)" :title="$article->title" :description="$article->summary" icon="file" eyebrow="Published reading" meta="Read online" action="Open article"/>
        @empty
            <p class="col-span-full rounded-3xl border border-dashed border-slate-300 bg-[#f8fafc] p-10 text-sm text-slate-500 dark:border-slate-700 dark:bg-[#193654]">No published library article matches this search. <a href="{{ route('learning.library') }}" class="font-bold underline">Reset search</a>.</p>
        @endforelse
    </div>
    <div class="mt-8">{{ $articles->links() }}</div>
</x-learning-section>
<x-learning-section :title="$a['courses_heading']" eyebrow="Structured learning" tone="mist" :action-url="route('courses.index')" action-label="All approved courses" subtitle="Explore reviewed course outlines, learning levels and enrollment requirements. Lessons require account authorization.">
    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($courses as $course)
            <x-public-course-card :course="$course"/>
        @empty
            <p class="col-span-full rounded-2xl border border-dashed border-slate-300 p-8 text-sm text-slate-600 dark:border-slate-700 dark:text-slate-300">No academic courses have been approved for public discovery yet.</p>
        @endforelse
    </div>
</x-learning-section>
<x-learning-section title="Explore by learning subject" eyebrow="Learning taxonomy" tone="white" subtitle="Explore verified published categories rather than unlicensed third-party textbook libraries.">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @forelse($categories as $category)
            <x-learning-card :href="route('categories.show',$category)" :title="$category->name" :description="$category->description" icon="tags" :eyebrow="$category->code" action="Explore category"/>
        @empty
            <p class="col-span-full text-sm text-slate-500">No public subject categories are available yet.</p>
        @endforelse
    </div>
</x-learning-section>
<x-learning-section :title="$a['access_heading']" eyebrow="Responsible resource access" tone="navy" subtitle="Document downloads, paid textbooks and offline synchronization are not presented as available until actual files, rights and secure access policies are reviewed.">
    <div class="grid gap-5 md:grid-cols-3">
        <x-learning-card :href="route('information.show','privacy')" title="Private learning records" description="Personal progress and coursework remain scoped to the authorized learning account." icon="lock"/>
        <x-learning-card :href="route('learning.reading')" title="Read online" description="Open published educational articles with readable, accessible typography." icon="book-open"/>
        <x-learning-card :href="route('cefr.index')" title="Reference frameworks" description="Consult published CEFR language-level reference information." icon="award"/>
    </div>
</x-learning-section>
<x-learning-section :title="$a['cta_heading']" eyebrow="More learning" tone="mist" :action-url="route('learning.reading')" action-label="Reading hub" subtitle="Build your learning pathway from genuine reviewed materials, courses and reading guides.">
    <div class="grid gap-5 sm:grid-cols-2">
        <x-learning-card :href="route('learning.exams')" title="Practice assessment directory" description="See courses with approved practice questions; no official examination claims." icon="clipboard-check"/>
        <x-learning-card :href="route('learning.recommendations')" title="Recommended pathways" description="Explore learning suggestions based on your own enrollment history when signed in." icon="compass"/>
    </div>
</x-learning-section>
@endsection
