@extends('layouts.public')
@section('title',\App\Support\Appearance::value('blog','hero_heading'))
@section('content')
@php $a=\App\Support\Appearance::approved('blog'); @endphp
<x-learning-hero :title="$a['hero_heading']" :subtitle="$a['hero_intro']" eyebrow="Blog & educational insights" icon="newspaper" :action-url="route('articles.index').'#article-directory'" action-label="Explore published articles"
    :stats="[
        ['value'=>number_format($publishedCount),'label'=>'Published articles','icon'=>'newspaper'],
        ['value'=>number_format($articles->total()),'label'=>'Matching published articles','icon'=>'search'],
    ]"/>
<x-learning-section :title="$a['feature_heading']" eyebrow="Editorial spotlight" subtitle="The most recently approved public article is featured here. No invented ratings, views or publishing statistics." tone="white">
    @if($featured)
        <div class="grid overflow-hidden rounded-3xl border border-slate-200 bg-[#f8fafc] shadow-md dark:border-slate-700 dark:bg-[#183658] lg:grid-cols-[minmax(0,1.15fr)_minmax(260px,.85fr)]">
            <div class="flex flex-col justify-center p-7 sm:p-10 lg:p-12">
                <span class="text-xs font-black uppercase tracking-widest text-[#b27b24]">Independently reviewed article</span>
                <h3 class="mt-5 text-2xl font-black leading-tight text-[#0d335d] dark:text-white sm:text-3xl">{{ $featured->title }}</h3>
                <p class="mt-5 text-sm leading-9 text-slate-600 dark:text-slate-300">{{ $featured->summary }}</p>
                <a href="{{ route('articles.show',$featured->slug) }}" class="mt-7 self-start rounded-xl bg-[#133967] px-6 py-3.5 text-xs font-black text-white hover:bg-[#235587]">Read the full article →</a>
            </div>
            <div class="flex min-h-64 flex-col items-center justify-center bg-gradient-to-br from-[#0c315d] to-[#1c5080] p-10 text-center text-white">
                <x-workspace-icon name="book-open" class="h-20 w-20 text-[#f1c46f]"/>
                <p class="mt-5 text-xl font-black">Educational reading</p>
                <p class="mt-3 max-w-sm text-sm leading-7 text-[#d6e7f4]">Published from the academic editorial workflow, with careful attribution and learner privacy.</p>
            </div>
        </div>
    @else
        <p class="rounded-2xl border border-dashed border-slate-300 p-9 text-sm text-slate-600 dark:border-slate-700 dark:text-slate-300">There are no independently published educational articles yet.</p>
    @endif
</x-learning-section>
<x-learning-section id="article-directory" :title="$a['articles_heading']" eyebrow="Reading library" subtitle="Filter by real article titles and summaries. Only published entries are displayed." tone="mist">
    <form action="{{ route('articles.index') }}#article-directory" method="GET" role="search" class="flex max-w-3xl flex-wrap gap-3">
        <label for="blog-search" class="sr-only">Search published articles</label>
        <input id="blog-search" name="q" type="search" maxlength="80" value="{{ $search }}" placeholder="Search learning articles and tips" class="min-w-[200px] flex-1 rounded-xl border border-slate-300 bg-white px-5 py-3.5 text-sm dark:border-slate-700 dark:bg-[#173451]">
        <button type="submit" class="rounded-xl bg-[#11365f] px-6 py-3.5 text-sm font-black text-white">Search</button>
        @if($search!=='')<a href="{{ route('articles.index') }}#article-directory" class="rounded-xl border border-slate-300 px-5 py-3.5 text-xs font-bold dark:border-slate-600">Clear</a>@endif
    </form>
    <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @forelse($articles as $article)
            <x-learning-card :href="route('articles.show',$article->slug)" :title="$article->title" :description="$article->summary" icon="newspaper" eyebrow="Published article" meta="Educational reading" action="Read article"/>
        @empty
            <p class="col-span-full rounded-3xl border border-dashed border-slate-300 bg-white p-10 text-sm text-slate-600 dark:border-slate-700 dark:bg-[#153254] dark:text-slate-300">No published articles match your search. <a href="{{ route('articles.index') }}" class="font-bold text-[#b27c25] underline">Browse all articles</a>.</p>
        @endforelse
    </div>
    <div class="mt-8">{{ $articles->links() }}</div>
</x-learning-section>
<x-learning-section :title="$a['resources_heading']" eyebrow="More ways to learn" tone="navy" subtitle="These links open existing educational information and course pages—not speculative downloads or chat groups.">
    <div class="grid gap-5 md:grid-cols-3">
        @foreach([
            ['book-open','Reviewed courses','Browse current approved course outlines, access requirements and learning topics',route('courses.index')],
            ['award','CEFR learning levels','Understand the A1–C2 reference framework without suggesting official assessment results',route('cefr.index')],
            ['users','Education contributors','Explore verified, voluntary contributor biographies and published educational articles',route('authors.index')],
        ] as [$icon,$heading,$desc,$href])
            <x-learning-card :icon="$icon" :title="$heading" :description="$desc" :href="$href" action="Open resource"/>
        @endforeach
    </div>
</x-learning-section>
<x-learning-section title="Questions about educational articles" eyebrow="Reader guidance" tone="white">
    <div class="grid gap-4 lg:grid-cols-2">
        @foreach([
            ['Who reviews articles before they are published?','Editorial content must pass the existing independent administrator review process before appearing publicly.'],
            ['Are article downloads or social groups available?','Only public text articles and links shown as available are supported. Unverified PDF downloads, messaging groups and memberships are not offered.'],
            ['Can I submit a learning question?','You can use the protected inquiry form to contact the platform team. Do not include passwords or private records.'],
            ['Do articles award qualifications?','No. Public education articles do not provide official test scores, certification or institutional accreditation.'],
        ] as [$question,$answer])
        <details class="group rounded-2xl border border-slate-200 bg-[#f8fafc] p-6 dark:border-slate-700 dark:bg-[#173655]">
            <summary class="flex cursor-pointer items-center justify-between gap-3 text-sm font-black">{{ $question }}<x-workspace-icon name="chevron-down" class="h-5 w-5 shrink-0 group-open:rotate-180"/></summary>
            <p class="mt-4 text-sm leading-8 text-slate-600 dark:text-slate-300">{{ $answer }}</p>
        </details>
        @endforeach
    </div>
</x-learning-section>
<x-learning-section :title="$a['cta_heading']" eyebrow="Continue your journey" tone="mist"  :action-url="route('learning.reading')" action-label="Reading library" subtitle="Explore published reading materials and follow learning pathways suited to your interests.">
    <div class="grid gap-5 sm:grid-cols-2">
        <x-learning-card :href="route('learning.reading')" title="Reading and literacy" description="Browse published reading resources and practical study habits." icon="book-open" action="Read more"/>
        <x-learning-card :href="route('learning.library')" title="Digital learning library" description="Discover reviewed articles, course outlines and learning frameworks." icon="library" action="Open library"/>
    </div>
</x-learning-section>
@endsection
