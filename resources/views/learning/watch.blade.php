@extends('layouts.public')
@section('title',\App\Support\Appearance::value('course_watch','hero_heading'))
@section('content')
@php $a=\App\Support\Appearance::approved('course_watch'); @endphp
<x-learning-hero :title="$a['hero_heading']" :subtitle="$a['hero_intro']" eyebrow="Course Watch / Lesson study" icon="book-open" :action-url="'#learning-courses'" action-label="Explore learning lessons"
    :stats="[
        ['value'=>number_format($courses->count()),'label'=>'Featured approved learning courses','icon'=>'book-open'],
        ['value'=>$student?number_format($enrollments->count()):'—','label'=>'Your active course enrollments','icon'=>'user-check'],
    ]"/>
<x-learning-section id="learning-courses" :title="$a['curriculum_heading']" eyebrow="Real course access" tone="white" subtitle="The focused lesson workspace uses existing protected course text, modules and progress records. There is no unapproved video streaming, live chat or media upload.">
    @if($student && $enrollments->isNotEmpty())
        <h3 class="mb-6 text-xl font-black text-[#12345c] dark:text-white">Continue your own approved courses</h3>
        <div class="grid gap-5 lg:grid-cols-2">
            @foreach($enrollments as $enrollment)
                <article class="rounded-3xl border border-[#dabb79] bg-[#fffaf0] p-7 shadow-sm dark:border-[#816a40] dark:bg-[#3d3423]">
                    <p class="text-xs font-black uppercase tracking-wider text-[#a47728]">Approved enrollment</p>
                    <h4 class="mt-3 text-xl font-black text-[#12345c] dark:text-white">{{ $enrollment->course->title }}</h4>
                    <p class="mt-3 text-sm leading-8 text-slate-600 dark:text-slate-300">{{ $enrollment->course->summary }}</p>
                    <div class="mt-5 flex flex-wrap gap-3">
                        @php $firstLesson=$enrollment->course->modules->flatMap(fn($m)=>$m->lessons)->first(); @endphp
                        @if($firstLesson)
                            <a href="{{ route('account.lessons.watch',[$enrollment,$firstLesson]) }}" class="rounded-xl bg-[#123862] px-5 py-3 text-xs font-black text-white">Open focused lesson</a>
                        @endif
                        <a href="{{ route('account.enrollments.show',$enrollment) }}" class="rounded-xl border border-[#d5ac60] px-5 py-3 text-xs font-black text-[#a57829] dark:text-[#f3c777]">Open all modules</a>
                    </div>
                </article>
            @endforeach
        </div>
    @else
        <div class="rounded-3xl border border-slate-200 bg-[#f7f9fc] p-8 dark:border-slate-700 dark:bg-[#183757]">
            <h3 class="text-xl font-black">Start a learning pathway</h3>
            <p class="mt-3 text-sm leading-9 text-slate-600 dark:text-slate-300">There are no approved learner courses to resume for this session. Choose an available published course and request enrollment as a student.</p>
            <a href="{{ route('courses.index') }}" class="mt-5 inline-block rounded-xl bg-[#123a65] px-6 py-3 text-xs font-black text-white">Browse approved courses</a>
        </div>
    @endif
    <h3 class="mb-5 mt-12 text-xl font-black text-[#12345c] dark:text-white">Explore published course outlines</h3>
    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($courses as $course)
            <x-public-course-card :course="$course"/>
        @empty
            <p class="col-span-full text-sm text-slate-600 dark:text-slate-300">No public courses are available yet.</p>
        @endforelse
    </div>
</x-learning-section>
<x-learning-section :title="$a['features_heading']" eyebrow="Learning workspace" tone="mist" subtitle="Use real in-platform features, each with its own permissions and historical records.">
    <div class="grid gap-5 md:grid-cols-3">
        <x-learning-card :href="$student?route('account.progress.index'):route('login')" title="Course lesson progress" description="Mark a course lesson complete or reopen it for further study, scoped to your own approved enrollment." icon="chart-line"/>
        <x-learning-card :href="$student?route('account.practice.index'):route('login')" title="Practice quizzes" description="Submit securely graded learning checks; answer keys remain hidden before submission." icon="list-check"/>
        <x-learning-card :href="$student?route('account.homework.index'):route('login')" title="Approved assignments" description="Find and submit reviewed assignments in your authorized student workspace." icon="notebook-pen"/>
    </div>
</x-learning-section>
<x-learning-section :title="$a['privacy_heading']" eyebrow="Security and accessibility" tone="navy" subtitle="Unlike the visual reference, this version does not invent a video source, closed-caption track, Telegram feed or rights-cleared downloadable file.">
    <div class="grid gap-5 md:grid-cols-3">
        <x-learning-card :href="route('information.show','privacy')" title="Protected lessons" description="Lesson content is available only to the owner of an approved, active enrollment." icon="lock"/>
        <x-learning-card :href="route('learning.exams')" title="Reviewed assessments" description="Practice-question material is available only through the authorized assessment flow." icon="clipboard-check"/>
        <x-learning-card :href="route('information.show','contact')" title="Private learner help" description="Use the safe inquiry form for educational or access questions." icon="life-buoy"/>
    </div>
</x-learning-section>
<x-learning-section title="How to study with Course Watch" eyebrow="Step-by-step support" tone="white">
    <div class="grid gap-5 md:grid-cols-3">
        @foreach([
            ['user-check','1. Request enrollment','An administrator approves access to the published course.'],
            ['book-open','2. Open a lesson','The text, duration and navigation are shown in the protected learner view.'],
            ['check-check','3. Record completion','Mark your own progress and open optional course practice questions.'],
        ] as [$icon,$heading,$description])
            <div class="rounded-3xl border border-slate-200 bg-[#f8fafc] p-7 dark:border-slate-700 dark:bg-[#183759]">
                <x-workspace-icon :name="$icon" class="h-10 w-10 text-[#aa792b]"/><h3 class="mt-5 text-lg font-black">{{ $heading }}</h3>
                <p class="mt-3 text-sm leading-8 text-slate-600 dark:text-slate-300">{{ $description }}</p>
            </div>
        @endforeach
    </div>
</x-learning-section>
<x-learning-section :title="$a['cta_heading']" eyebrow="Continue studying" tone="mist" :action-url="$student?route('account.enrollments.index'):route('courses.index')" :action-label="$student?'My enrollments':'Browse courses'" subtitle="Access approved lesson content and learning progress only through your own authorized student account."/>
@endsection
