@props(['course'])
<article class="premium-card group flex h-full min-w-0 flex-col rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-[#e4b456] hover:shadow-xl dark:border-slate-800 dark:bg-slate-900">
    <span class="premium-corner-icon" aria-hidden="true"><x-workspace-icon name="book-open" /></span>
    <div class="premium-card-content flex flex-wrap items-center gap-2 text-xs font-bold">
        <span class="rounded-full bg-[#fff2d1] px-3 py-1 text-[#8b5a12] dark:bg-amber-950 dark:text-amber-300">{{ $course->category?->code }}</span>
        @if($course->cefr_level)<span class="rounded-full bg-slate-100 px-3 py-1 text-slate-600 dark:bg-slate-800 dark:text-slate-300">CEFR {{ $course->cefr_level }}</span>@endif
        <span class="ml-auto text-slate-400">{{ ucfirst($course->delivery_mode) }}</span>
    </div>
    <h3 class="premium-card-content mt-5 text-xl font-black leading-snug text-[#0d2d5b] dark:text-white"><a href="{{ route('courses.show',$course->slug) }}" class="rounded focus-visible:outline-2 focus-visible:outline-blue-600 hover:underline">{{ $course->title }}</a></h3>
    <p class="premium-card-content mt-3 flex-1 text-sm leading-7 text-slate-600 dark:text-slate-400">{{ $course->summary }}</p>
    <div class="premium-card-content mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <span class="text-xs font-semibold text-slate-500">{{ $course->lessons_count ?? $course->lessons()->count() }} {{ __('frontend.lessons') }}</span>
        <a href="{{ route('courses.show',$course->slug) }}" class="inline-flex items-center gap-1 rounded-full bg-[#0c2d5b] px-4 py-2.5 text-xs font-bold text-white transition hover:bg-[#1c4a83] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#dca64e]">{{ __('frontend.view_course') }}</a>
    </div>
</article>
