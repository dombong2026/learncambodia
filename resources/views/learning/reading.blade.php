@extends('layouts.public')
@section('title',\App\Support\Appearance::value('reading','hero_heading'))
@section('content')
@php $a=\App\Support\Appearance::approved('reading'); @endphp
<x-learning-hero :title="$a['hero_heading']" :subtitle="$a['hero_intro']" eyebrow="Reading and literacy" icon="book-open" :action-url="'#reading-library'" action-label="Explore reading materials"
    :stats="[
        ['value'=>number_format($articles->total()),'label'=>'Matching published readings','icon'=>'newspaper'],
        ['value'=>number_format($categories->count()),'label'=>'Featured study categories','icon'=>'tags'],
    ]"/>
<x-learning-section id="reading-library" :title="$a['articles_heading']" eyebrow="Reader-friendly education" tone="white" subtitle="Reading selections come from genuinely published educational articles. Private library files and copyrighted textbook downloads are not implied.">
    <form role="search" action="{{ route('learning.reading') }}#reading-library" method="GET" class="flex max-w-3xl gap-2">
        <label for="reading-search" class="sr-only">Find published reading</label>
        <input type="search" id="reading-search" name="q" value="{{ $search }}" maxlength="80" placeholder="Search published readings" class="min-w-0 flex-1 rounded-xl border border-slate-300 bg-white px-5 py-3.5 text-sm dark:border-slate-700 dark:bg-[#163453]">
        <button type="submit" class="rounded-xl bg-[#10355d] px-5 py-3.5 text-sm font-black text-white">Find reading</button>
    </form>
    <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @forelse($articles as $article)
            <x-learning-card :href="route('articles.show',$article->slug)" :title="$article->title" :description="$article->summary" icon="book-open" eyebrow="Read online" action="Start reading"/>
        @empty
            <p class="col-span-full rounded-3xl border border-dashed border-slate-300 p-10 text-sm text-slate-500 dark:border-slate-700">No approved reading materials match your search.</p>
        @endforelse
    </div>
    <div class="mt-8">{{ $articles->links() }}</div>
</x-learning-section>
<x-learning-section :title="$a['methods_heading']" eyebrow="Simple reading practice" tone="mist" subtitle="Study suggestions are optional guidance, not recorded scores or mandatory tasks.">
    <div class="grid gap-5 md:grid-cols-3">
        @foreach([
            ['book-open','Read with a purpose','Choose one approved article and identify its central topic and supporting information.'],
            ['pen-square','Summarize in your own words','After reading, write a short explanation of the main ideas and any unfamiliar terms.'],
            ['chart-line','Reflect and revisit','Re-read difficult passages, review vocabulary and study in manageable sessions.'],
        ] as [$icon,$heading,$description])
            <div class="premium-card rounded-3xl border border-slate-200 bg-white p-8 shadow-sm dark:border-slate-700 dark:bg-[#173757]">
                <span class="grid h-14 w-14 place-items-center rounded-xl bg-[#f8e7bf] text-[#a67b2b] dark:bg-[#493d27]"><x-workspace-icon :name="$icon" class="h-7 w-7"/></span>
                <h3 class="mt-6 text-xl font-black text-[#11375d] dark:text-white">{{ $heading }}</h3>
                <p class="mt-4 text-sm leading-8 text-slate-600 dark:text-slate-300">{{ $description }}</p>
            </div>
        @endforeach
    </div>
</x-learning-section>
<x-learning-section :title="$a['pathways_heading']" eyebrow="Explore learning pathways" tone="navy" :action-url="route('categories.index')" action-label="All categories" subtitle="Start with a subject, review existing course material and continue to approved learning when ready.">
    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        @forelse($categories as $category)
            <x-learning-card :href="route('categories.show',$category)" :title="$category->name" :description="$category->description" icon="graduation" :eyebrow="$category->code" action="Explore pathway"/>
        @empty
            <p class="col-span-full text-sm text-[#d6e5f1]">There are no published categories yet.</p>
        @endforelse
    </div>
</x-learning-section>
<x-learning-section title="Reading guidance and FAQs" eyebrow="Accessible reading" tone="white">
    <div class="grid gap-4 md:grid-cols-2">
        @foreach([
            ['Are these readings free to browse?','Published educational articles can be viewed online. Some future copyrighted downloads may have separate authorization requirements.'],
            ['Are these materials official school textbooks?','No official textbook endorsement is claimed. Materials are reviewed editorial content and course information.'],
            ['Can I save reading progress?','Current student progress tracking applies to authorized course lessons. Article bookmarks and reading streaks are not yet implemented.'],
            ['Can I read on mobile?','These pages use responsive layouts with selectable text and keyboard-accessible controls. Physical-device acceptance is still required.'],
        ] as [$q,$answer])
            <details class="group rounded-2xl border border-slate-200 bg-[#f8fafc] p-6 dark:border-slate-700 dark:bg-[#193759]">
                <summary class="flex cursor-pointer items-center justify-between gap-3 text-sm font-black">{{ $q }}<x-workspace-icon name="chevron-down" class="h-5 w-5 shrink-0 group-open:rotate-180"/></summary><p class="mt-4 text-sm leading-8 text-slate-600 dark:text-slate-300">{{ $answer }}</p>
            </details>
        @endforeach
    </div>
</x-learning-section>
<x-learning-section :title="$a['cta_heading']" eyebrow="Learning resources" tone="mist" :action-url="route('learning.library')" action-label="Open full library" subtitle="Browse the public educational library and explore real course pathways.">
    <div class="grid gap-5 md:grid-cols-3">
        <x-learning-card :href="route('learning.library')" title="Digital learning library" description="Reviewed published learning materials and course outlines." icon="library"/>
        <x-learning-card :href="route('articles.index')" title="Educational articles" description="Search the published editorial article collection." icon="newspaper"/>
        <x-learning-card :href="route('courses.index')" title="Structured courses" description="Explore reviewed curriculums with protected lesson access." icon="book-open"/>
    </div>
</x-learning-section>
@endsection
