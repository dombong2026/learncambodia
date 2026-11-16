@props(['article'])

<a href="{{ route('articles.show', $article->slug) }}" class="group flex h-full flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm hover:border-[#dcb25e] focus-visible:ring-2 focus-visible:ring-blue-600 dark:border-slate-700 dark:bg-[#183b60]">
    @if($article->featured_kind === 'image' && $article->featuredMedia)
        <img class="aspect-video w-full object-cover" src="{{ route('editor-media.show', $article->featuredMedia) }}" alt="{{ $article->featured_alt ?? '' }}" loading="lazy" decoding="async">
    @elseif($article->featured_kind === 'color' && preg_match('/^#[a-f0-9]{6}$/D', $article->featured_value ?? ''))
        <div class="aspect-video w-full" style="background-color: {{ $article->featured_value }}" aria-hidden="true"></div>
    @endif
    <div class="flex flex-1 flex-col p-6">
        <h3 class="text-xl font-black leading-snug text-[#12345e] group-hover:underline dark:text-white">{{ $article->title }}</h3>
        <p class="mt-3 flex-1 text-sm leading-7 text-slate-600 dark:text-slate-300">{{ $article->summary }}</p>
        <span class="mt-5 text-xs font-bold text-[#976711] dark:text-[#f3c56f]">{{ __('frontend.read_article') }}</span>
    </div>
</a>
