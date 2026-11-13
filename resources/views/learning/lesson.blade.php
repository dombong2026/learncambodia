@extends('layouts.public')
@section('title',$lesson->title.' · '.$course->title)
@section('content')
@php
    $a=\App\Support\Appearance::approved('course_watch');
    $totalLessons=$course->modules->sum(fn($m)=>$m->lessons->count());
    $percent=$totalLessons>0?round(count($completedIds)*100/$totalLessons):0;
    $done=in_array($lesson->id,$completedIds,true);
@endphp
<x-learning-hero :title="$lesson->title" :subtitle="$course->title" eyebrow="Course Watch / Protected lesson" icon="book-open"
    :action-url="route('account.enrollments.show',$enrollment)" action-label="Back to full curriculum"
    :stats="[
        ['value'=>number_format($lesson->duration_minutes).' min','label'=>'Lesson study duration','icon'=>'calendar'],
        ['value'=>$percent.'%','label'=>'Your self-reported course completion','icon'=>'chart-line'],
    ]"/>
<x-learning-section id="lesson-content" title="Focused learning workspace" eyebrow="Approved student enrollment" tone="white" subtitle="This private view is available only to the owner of an active approved enrollment. Lesson text is presented as safe plain text.">
    <div class="grid items-start gap-8 lg:grid-cols-[minmax(0,1.6fr)_minmax(280px,.65fr)]">
        <article class="min-w-0 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-9 dark:border-slate-700 dark:bg-[#173758]">
            <div class="mb-8 flex flex-wrap items-center justify-between gap-3">
                <span class="rounded-xl bg-[#f8eac9] px-4 py-2 text-xs font-black text-[#926b2b]">{{ $done?'Lesson marked complete':'Lesson in progress' }}</span>
                <span class="text-xs font-semibold text-slate-500">{{ $lesson->duration_minutes }} minutes</span>
            </div>
            <h2 class="text-2xl font-black text-[#123661] dark:text-white">{{ $lesson->title }}</h2>
            <p class="mt-7 whitespace-pre-line break-words text-base leading-10 text-slate-700 dark:text-slate-200">{{ $lesson->content }}</p>
            <form action="{{ route('account.progress.update',[$enrollment,$lesson]) }}" method="POST" class="mt-9 flex flex-wrap justify-end gap-3">@csrf
                <input type="hidden" name="complete" value="{{ $done?'0':'1' }}">
                <button type="submit" class="rounded-xl px-6 py-3 text-xs font-black {{ $done?'border border-[#d6a660] text-[#a47625] dark:text-[#f2c575]':'bg-[#123a67] text-white' }}">{{ $done?'Mark for more study':'Mark this lesson complete' }}</button>
            </form>
            <div class="mt-7 flex flex-wrap justify-between gap-3 border-t border-slate-200 pt-6 dark:border-slate-700">
                @if($previous)<a href="{{ route('account.lessons.watch',[$enrollment,$previous]) }}" class="rounded-xl border border-slate-300 px-5 py-3 text-xs font-bold dark:border-slate-600">← Previous lesson</a>@else<span></span>@endif
                @if($next)<a href="{{ route('account.lessons.watch',[$enrollment,$next]) }}" class="rounded-xl bg-[#10355e] px-5 py-3 text-xs font-black text-white">Next lesson →</a>@endif
            </div>
        </article>
        <aside class="space-y-5 lg:sticky lg:top-40">
            <div class="rounded-3xl border border-slate-200 bg-[#f8fafc] p-6 dark:border-slate-700 dark:bg-[#173758]">
                <h3 class="text-lg font-black text-[#123660] dark:text-white">{{ $a['curriculum_heading'] }}</h3>
                <div class="mt-5 h-2.5 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-[#344961]"><div class="h-full rounded-full bg-[#d4a448]" style="width:{{ $percent }}%"></div></div>
                <p class="mt-2 text-xs font-bold text-slate-500 dark:text-slate-300">{{ count($completedIds) }} / {{ $totalLessons }} lessons marked complete</p>
                <nav class="mt-5 space-y-3" aria-label="Protected course curriculum">
                    @foreach($course->modules as $module)
                        <details class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-[#0e2c4c]" @if($module->lessons->contains('id',$lesson->id))open@endif>
                            <summary class="cursor-pointer text-xs font-black">{{ $module->title }}</summary>
                            <div class="mt-3 space-y-2">
                                @foreach($module->lessons as $item)
                                    <a href="{{ route('account.lessons.watch',[$enrollment,$item]) }}" class="block rounded-lg p-3 text-xs leading-6 {{ $item->id===$lesson->id?'bg-[#12345d] font-black text-white':'bg-[#f4f6f9] text-slate-700 hover:bg-[#f1e3c6] dark:bg-[#244461] dark:text-white' }}">
                                        {{ $item->title }}
                                        @if(in_array($item->id,$completedIds,true))<span aria-label="Completed"> ✓</span>@endif
                                    </a>
                                @endforeach
                            </div>
                        </details>
                    @endforeach
                </nav>
            </div>
            <div class="rounded-3xl bg-[#123760] p-6 text-white">
                <h3 class="text-lg font-black">Practice & assignments</h3>
                <p class="mt-3 text-xs leading-7 text-[#d2e2f0]">Practice results and assignments stay inside your own learning workspace.</p>
                <a href="{{ route('account.practice.index') }}" class="mt-5 block rounded-xl bg-[#efc26b] px-5 py-3 text-center text-xs font-black text-[#103359]">My practice quizzes</a>
                <a href="{{ route('account.homework.index') }}" class="mt-3 block rounded-xl border border-white/30 px-5 py-3 text-center text-xs font-bold">My assignments</a>
            </div>
        </aside>
    </div>
</x-learning-section>
<x-learning-section title="Related learning actions" eyebrow="Keep practicing" tone="mist">
    <div class="grid gap-5 md:grid-cols-3">
        <x-learning-card :href="route('account.progress.index')" title="View your progress" description="See completed lessons and enrolled courses." icon="chart-line"/>
        <x-learning-card :href="route('account.enrollments.show',$enrollment)" title="Return to full course" description="Review all approved course modules and lessons." icon="book-open"/>
        <x-learning-card :href="route('learning.library')" title="Public library" description="Explore independently reviewed general educational materials." icon="library"/>
    </div>
</x-learning-section>
@endsection
