@extends('layouts.public')
@section('title',$course->title.' · Practice Assessments')
@section('meta_description',$course->summary)
@section('content')
@php $a=\App\Support\Appearance::approved('single_course_exam'); @endphp
<x-learning-hero :title="$course->title" :subtitle="$course->summary" eyebrow="Course exam / Reviewed practice" icon="clipboard-check" :action-url="'#practice-quiz-list'" action-label="View course practice"
    :stats="[
        ['value'=>number_format($quizzes->count()),'label'=>'Reviewed practice quizzes','icon'=>'list-check'],
        ['value'=>number_format($quizzes->sum('questions_count')),'label'=>'Practice questions','icon'=>'clipboard-check'],
    ]"/>
<x-learning-section :title="$a['intro_heading']" eyebrow="About this course" tone="white" subtitle="The preparation area uses the existing reviewed quiz engine. Course content remains protected until your enrollment is approved.">
    <div class="grid gap-7 lg:grid-cols-[minmax(0,1.4fr)_minmax(260px,.6fr)]">
        <div class="rounded-3xl border border-slate-200 bg-[#f8fafc] p-8 dark:border-slate-700 dark:bg-[#183657]">
            <p class="text-xs font-black uppercase tracking-wider text-[#b07d28]">{{ $course->category?->name }}</p>
            <h3 class="mt-4 text-2xl font-black">{{ $course->title }}</h3>
            <p class="mt-4 whitespace-pre-line text-sm leading-9 text-slate-600 dark:text-slate-300">{{ $course->description }}</p>
            <div class="mt-6 flex flex-wrap gap-3 text-xs font-bold text-slate-500 dark:text-slate-300">
                @if($course->cefr_level)<span class="rounded-full border border-[#d9b778] px-4 py-2">CEFR {{ $course->cefr_level }}</span>@endif
                <span class="rounded-full border border-slate-200 px-4 py-2 dark:border-slate-700">{{ $course->lessons_count }} lessons</span>
                <span class="rounded-full border border-slate-200 px-4 py-2 dark:border-slate-700">{{ ucfirst($course->delivery_mode) }} delivery</span>
            </div>
        </div>
        <aside class="rounded-3xl bg-[#10345d] p-8 text-white">
            <h3 class="text-xl font-black">Secure assessment access</h3>
            <p class="mt-4 text-sm leading-8 text-[#d4e3ef]">Questions and answer keys remain protected by approved enrollment. Practice feedback is not an official exam result.</p>
            @if($enrollment)
                <a href="{{ route('account.enrollments.show',$enrollment) }}" class="mt-6 block rounded-xl bg-[#f1c673] px-5 py-3.5 text-center text-xs font-black text-[#0d3159]">Open approved course</a>
            @elseif(auth()->check() && auth()->user()->role==='student')
                <a href="{{ route('courses.show',$course->slug) }}" class="mt-6 block rounded-xl bg-[#f1c673] px-5 py-3.5 text-center text-xs font-black text-[#0d3159]">Request enrollment</a>
            @else
                <a href="{{ route('login') }}" class="mt-6 block rounded-xl bg-[#f1c673] px-5 py-3.5 text-center text-xs font-black text-[#0d3159]">Sign in to learn</a>
            @endif
        </aside>
    </div>
</x-learning-section>
<x-learning-section id="practice-quiz-list" :title="$a['quiz_heading']" eyebrow="In-platform learning checks" tone="mist" subtitle="Titles and number of questions are safe to discover. All assessment answers remain private until authorized submission.">
    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($quizzes as $quiz)
            <div class="flex flex-col rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-700 dark:bg-[#193757]">
                <div class="flex items-start justify-between"><span class="grid h-12 w-12 place-items-center rounded-xl bg-[#f5e4bc] text-[#ad8029]"><x-workspace-icon name="list-check" class="h-6 w-6"/></span><span class="rounded-full bg-[#f9efd9] px-3 py-1 text-[11px] font-black text-[#9c742d]">{{ $quiz->questions_count }} questions</span></div>
                <h3 class="mt-5 text-xl font-black text-[#11355e] dark:text-white">{{ $quiz->title }}</h3>
                <p class="mt-3 flex-1 text-sm leading-8 text-slate-600 dark:text-slate-300">{{ $quiz->instructions }}</p>
                <p class="mt-4 text-xs font-bold text-slate-500 dark:text-slate-300">Passing threshold: {{ $quiz->passing_percent }}% · Educational practice</p>
                @if($enrollment)
                    <a href="{{ route('account.practice.show',[$enrollment,$quiz]) }}" class="mt-6 rounded-xl bg-[#123b67] px-5 py-3 text-center text-xs font-black text-white">Start approved practice</a>
                @else
                    <a href="{{ route('courses.show',$course->slug) }}" class="mt-6 rounded-xl border border-[#d4ad66] px-5 py-3 text-center text-xs font-bold text-[#a77a29] dark:text-[#f5c878]">View enrollment requirements</a>
                @endif
            </div>
        @endforeach
    </div>
</x-learning-section>
<x-learning-section :title="$a['steps_heading']" eyebrow="Preparation path" tone="navy">
    <div class="grid gap-5 md:grid-cols-3">
        <x-learning-card :href="route('courses.show',$course->slug)" title="Study the course outline" description="Understand objectives, learning units and assessment context." icon="book-open"/>
        <x-learning-card :href="$enrollment?route('account.enrollments.show',$enrollment):route('login')" title="Review approved lessons" description="Only the enrolled learner may access private study content." icon="lock"/>
        <x-learning-card :href="$enrollment?route('account.practice.index'):route('login')" title="Complete your own practice" description="Submit answers securely and review results within your account." icon="clipboard-check"/>
    </div>
</x-learning-section>
<x-learning-section :title="$a['related_heading']" eyebrow="More approved study" tone="white" :action-url="route('learning.exams')" action-label="Exam catalog">
    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        @forelse($related as $entry)
            <x-public-course-card :course="$entry"/>
        @empty
            <p class="col-span-full text-sm text-slate-500">No other published courses are currently available in this subject category.</p>
        @endforelse
    </div>
</x-learning-section>
<x-learning-section :title="$a['cta_heading']" eyebrow="Academic practice" tone="mist" :action-url="route('courses.show',$course->slug)" action-label="Course details" subtitle="Practice quizzes are optional, limited attempts for learning, and never official examination certification.">
    <p class="text-sm leading-8 text-slate-600 dark:text-slate-300">For other educational materials, explore the <a href="{{ route('learning.library') }}" class="font-black text-[#a67b2d] underline">library</a> or <a href="{{ route('learning.reading') }}" class="font-black text-[#a67b2d] underline">reading hub</a>.</p>
</x-learning-section>
@endsection
