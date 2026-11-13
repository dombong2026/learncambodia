@extends('layouts.public')
@section('title',\App\Support\Appearance::value('course_exam_catalog','hero_heading'))
@section('content')
@php $a=\App\Support\Appearance::approved('course_exam_catalog'); @endphp
<x-learning-hero :title="$a['hero_heading']" :subtitle="$a['hero_intro']" eyebrow="Exam & practice preparation" icon="clipboard-check" :action-url="'#exam-courses'" action-label="Browse practice courses"
    :stats="[
        ['value'=>number_format($courses->total()),'label'=>'Matching courses with practice quizzes','icon'=>'book-open'],
        ['value'=>'4','label'=>'Choices per practice question','icon'=>'list-check'],
    ]"/>
<x-learning-section id="exam-courses" :title="$a['course_heading']" eyebrow="Genuine reviewed practice" subtitle="Courses below contain existing reviewed practice quizzes. Public pages show only metadata; answer keys are not exposed." tone="white">
    <form method="GET" role="search" action="{{ route('learning.exams') }}#exam-courses" class="flex max-w-3xl gap-2">
        <label for="exam-search" class="sr-only">Find courses with practice assessments</label>
        <input id="exam-search" type="search" name="q" value="{{ $search }}" maxlength="80" placeholder="Search published assessment topics" class="min-w-0 flex-1 rounded-xl border border-slate-300 bg-white px-5 py-3.5 text-sm dark:border-slate-700 dark:bg-[#183755]">
        <button type="submit" class="rounded-xl bg-[#123a65] px-6 py-3 text-sm font-bold text-white">Search</button>
    </form>
    <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @forelse($courses as $course)
            <x-learning-card :href="route('learning.exam.show',$course->slug)" :title="$course->title" :description="$course->summary" icon="clipboard-check" :eyebrow="$course->category?->name" :meta="$course->practice_quizzes_count.' reviewed practice quizzes'" action="View practice"/>
        @empty
            <p class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-[#f8fafc] p-10 text-sm text-slate-500 dark:border-slate-700 dark:bg-[#193757]">No approved courses with active practice quizzes match your search. <a href="{{ route('courses.index') }}" class="font-black underline">Explore all courses</a>.</p>
        @endforelse
    </div>
    <div class="mt-8">{{ $courses->links() }}</div>
</x-learning-section>
<x-learning-section :title="$a['format_heading']" eyebrow="Practice workflow" tone="mist" subtitle="These learning checks reuse the existing secure student assessment engine. They are not government or school examination bookings.">
    <div class="grid gap-5 md:grid-cols-3">
        @foreach([
            ['book-open','1. Choose a reviewed course','Explore a published learning program with reviewed practice questions.'],
            ['user-check','2. Obtain approved enrollment','Students request access and an administrator must approve the enrollment.'],
            ['clipboard-check','3. Practice and review','Answer questions in your student workspace and review your own result history.'],
        ] as [$icon,$title,$desc])
            <div class="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm dark:border-slate-700 dark:bg-[#173656]">
                <span class="grid h-14 w-14 place-items-center rounded-xl bg-[#f7e5bf] p-3.5 text-[#a4762b] dark:bg-[#4f432e]"><x-workspace-icon :name="$icon" class="h-7 w-7"/></span>
                <h3 class="mt-5 text-xl font-black text-[#11355f] dark:text-white">{{ $title }}</h3><p class="mt-4 text-sm leading-8 text-slate-600 dark:text-slate-300">{{ $desc }}</p>
            </div>
        @endforeach
    </div>
</x-learning-section>
<x-learning-section :title="$a['resources_heading']" eyebrow="Learn before you test" tone="navy">
    <div class="grid gap-5 md:grid-cols-3">
        <x-learning-card :href="route('learning.library')" title="Study library" description="Read approved educational resources and course outlines." icon="library"/>
        <x-learning-card :href="route('learning.reading')" title="Reading practice" description="Build comprehension with accessible, published educational reading." icon="book-open"/>
        <x-learning-card :href="route('courses.index')" title="All courses" description="Find published learning programs before taking practice quizzes." icon="graduation"/>
    </div>
</x-learning-section>
<x-learning-section title="Practice assessment questions" eyebrow="FAQs & access" tone="white">
    <div class="grid gap-4 md:grid-cols-2">
        @foreach([
            ['Are these official national examinations?','No. These are learning practice quizzes authored and reviewed within the platform.'],
            ['Can I see the answer key before answering?','No. Correct answers are kept on the server and shown only in the authorized result workflow.'],
            ['Can I attempt an assessment without enrollment?','No. You must sign in as a student and have an approved enrollment for that course.'],
            ['Are practice scores formal certificates?','No. Practice feedback is educational and does not award a formally recognized qualification.'],
        ] as [$q,$ans])
            <details class="group rounded-2xl border border-slate-200 bg-[#f8fafc] p-6 dark:border-slate-700 dark:bg-[#183757]">
                <summary class="flex cursor-pointer items-center justify-between gap-3 text-sm font-black">{{ $q }}<x-workspace-icon name="chevron-down" class="h-5 w-5 shrink-0 group-open:rotate-180"/></summary>
                <p class="mt-4 text-sm leading-8 text-slate-600 dark:text-slate-300">{{ $ans }}</p>
            </details>
        @endforeach
    </div>
</x-learning-section>
<x-learning-section :title="$a['cta_heading']" eyebrow="Ready to learn?" tone="mist" :action-url="route('courses.index')" action-label="View approved courses" subtitle="Build knowledge from published course material before attempting secure practice.">
    @auth
        @if(auth()->user()->role==='student')<a href="{{ route('account.practice.index') }}" class="inline-block rounded-xl bg-[#133c68] px-7 py-3 text-sm font-bold text-white">My approved practice quizzes →</a>@endif
    @else
        <a href="{{ route('register') }}" class="inline-block rounded-xl bg-[#133c68] px-7 py-3 text-sm font-bold text-white">Create a student account →</a>
    @endauth
</x-learning-section>
@endsection
