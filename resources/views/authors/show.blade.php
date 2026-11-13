@extends('layouts.public')
@section('title', $profile->display_name.' · '.__('longpage.authors'))
@section('content')
@php $appearance=\App\Support\Appearance::approved('author'); @endphp
<section class="bg-gradient-to-br from-[#0a2850] via-[#163e71] to-[#0e2d54] text-white">
    <div class="mx-auto max-w-[1600px] px-4 py-14 sm:px-8 lg:px-12 lg:py-20">
        <nav aria-label="Breadcrumb" class="mb-10 flex flex-wrap gap-2 text-xs text-[#d1e2f0]"><a href="{{ route('home') }}" class="hover:underline">{{ __('longpage.home') }}</a><span>/</span><a href="{{ route('authors.index') }}" class="hover:underline">{{ __('longpage.authors') }}</a><span>/</span><span aria-current="page" class="font-bold text-[#f3c775]">{{ $profile->display_name }}</span></nav>
        <div class="flex flex-col items-start gap-8 sm:flex-row sm:items-center">
            <div class="grid h-32 w-32 shrink-0 place-items-center rounded-full border-[6px] border-[#f1c76f] bg-[#285a90] text-5xl font-black text-white shadow-xl" aria-hidden="true">{{ mb_strtoupper(mb_substr($profile->display_name,0,1)) }}</div>
            <div class="min-w-0">
                <p class="text-xs font-extrabold uppercase tracking-widest text-[#f3c971]">{{ __('longpage.author_profile_approved') }}</p>
                <h1 class="mt-3 break-words text-4xl font-black sm:text-5xl">{{ $profile->display_name }}</h1>
                <p class="mt-4 text-base leading-8 text-[#d5e3f0]">{{ $profile->headline }}</p>
                @if($profile->subjects)<p class="mt-3 text-sm font-bold text-[#f2c875]">{{ $profile->subjects }}</p>@endif
            </div>
            <div class="ml-auto rounded-2xl border border-white/20 bg-white/10 p-6 text-center">
                <p class="text-4xl font-black tabular-nums">{{ \App\Support\DashboardNumber::compact($articles->total()) }}</p><p class="mt-2 text-xs font-bold text-[#d9e6f3]">{{ __('longpage.published_articles') }}</p>
            </div>
        </div>
    </div>
</section>

<section class="bg-white py-16 dark:bg-[#0c2948]">
    <div class="mx-auto max-w-[1600px] px-4 sm:px-8 lg:px-12">
        <h2 class="text-3xl font-black text-[#113560] dark:text-white">{{ __('longpage.author_bio_heading') }}</h2>
        <p class="mt-5 max-w-5xl whitespace-pre-line break-words text-base leading-10 text-slate-600 dark:text-slate-300">{{ $profile->bio ?: __('people.no_bio') }}</p>
        <p class="mt-6 rounded-2xl border border-[#e6d0a3] bg-[#fff9ec] p-5 text-xs leading-8 text-[#73551e] dark:border-[#6c5935] dark:bg-[#443623] dark:text-[#f5d58f]">{{ __('longpage.author_profile_privacy') }}</p>
    </div>
</section>

<section class="bg-[#f1f4f9] py-16 dark:bg-[#0d2b4b]">
    <div class="mx-auto max-w-[1600px] px-4 sm:px-8 lg:px-12">
        <div class="flex flex-wrap items-end justify-between gap-4"><div><p class="text-xs font-black uppercase tracking-widest text-[#b07c25]">{{ __('longpage.author_works_label') }}</p><h2 class="mt-2 text-3xl font-black text-[#10335d] dark:text-white">{{ $appearance['work_heading'] }}</h2></div><a href="{{ route('authors.index') }}" class="rounded-xl border border-[#d4a85b] px-5 py-3 text-sm font-bold text-[#b17e28]">{{ __('longpage.all_authors') }}</a></div>
        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach($articles as $article)
                <a href="{{ route('articles.show',$article->slug) }}" class="group flex flex-col rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-[#e2b868] dark:border-slate-700 dark:bg-[#173657]">
                    <p class="text-xs font-black text-[#b07b26]">{{ __('longpage.approved_article') }}</p>
                    <h3 class="mt-4 text-lg font-black text-[#12355e] group-hover:underline dark:text-white">{{ $article->title }}</h3>
                    <p class="mt-3 flex-1 text-sm leading-8 text-slate-600 dark:text-slate-300">{{ $article->summary }}</p>
                    <span class="mt-5 text-xs font-bold text-[#a87a2e]">{{ __('frontend.read_article') }} →</span>
                </a>
            @endforeach
        </div>
        <div class="mt-8">{{ $articles->links() }}</div>
    </div>
</section>

<section class="bg-[#103760] py-14 text-white">
    <div class="mx-auto flex max-w-[1600px] flex-col justify-between gap-6 px-4 sm:px-8 lg:flex-row lg:items-center lg:px-12">
        <div><h2 class="text-3xl font-black">{{ __('longpage.author_more_learning') }}</h2><p class="mt-3 text-sm leading-8 text-[#d1e0ef]">{{ __('longpage.author_more_learning_note') }}</p></div>
        <div class="flex flex-wrap gap-3"><a href="{{ route('authors.index') }}" class="rounded-xl border border-white/30 px-6 py-3 text-sm font-bold">{{ __('longpage.all_authors') }}</a><a href="{{ route('courses.index') }}" class="rounded-xl bg-[#efc270] px-6 py-3 text-sm font-black text-[#11315a]">{{ __('longpage.browse_course_catalog') }}</a></div>
    </div>
</section>
@endsection
