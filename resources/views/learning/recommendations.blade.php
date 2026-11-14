@extends('layouts.public')
@section('title',\App\Support\Appearance::value('recommendations','hero_heading'))
@section('content')
@php
    $a=\App\Support\Appearance::approved('recommendations');
    $approvedCount=$enrollments->where('status','approved')->count();
@endphp
<x-learning-hero :title="$a['hero_heading']" :subtitle="$a['hero_intro']" eyebrow="Recommendations / Learning pathways" icon="compass"
    :action-url="'#recommended-courses'" action-label="Explore learning pathways"
    :stats="[
        ['value'=>$student?number_format($approvedCount):'—','label'=>'Your approved course enrollments','icon'=>'book-open'],
        ['value'=>$student?number_format($completedLessons):'—','label'=>'Your completed lessons','icon'=>'check-check'],
    ]"/>
<x-learning-section id="recommended-courses" :title="$a['courses_heading']" eyebrow="Relevant learning opportunities" tone="white" :action-url="route('courses.index')" action-label="All published courses"
    :subtitle="$student && $enrollments->isNotEmpty()
        ? 'These matching courses share a category with your existing enrollments. This is a transparent category-based recommendation, not an AI judgment or official placement.'
        : 'Explore genuinely published study opportunities. Sign in and enroll to receive category-based learning suggestions from your own study history.'">
    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @forelse($suggestions as $course)
            <x-public-course-card :course="$course"/>
        @empty
            <div class="col-span-full rounded-3xl border border-dashed border-slate-300 bg-[#f8fafc] p-10 text-sm leading-8 text-slate-600 dark:border-slate-700 dark:bg-[#163454] dark:text-slate-300">
                No additional published course currently matches your enrolled learning categories.
                <a href="{{ route('categories.index') }}" class="ml-1 font-black text-[#a87a29] underline">Explore different subjects</a>.
            </div>
        @endforelse
    </div>
</x-learning-section>
<x-learning-section :title="$a['activity_heading']" eyebrow="Own account data only" tone="mist" :action-url="$student?route('account.progress.index'):route('login')" :action-label="$student?'Open my progress':'Sign in to view progress'"
    subtitle="Only your own enrollments, completion checkmarks and private practice results are visible here. We do not infer performance from other people.">
    @if($student)
        <div class="grid gap-7 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,.7fr)]">
            <div class="rounded-3xl border border-slate-200 bg-white p-7 dark:border-slate-700 dark:bg-[#173655]">
                <h3 class="text-xl font-black text-[#123660] dark:text-white">Your recent enrollment pathways</h3>
                <div class="mt-6 space-y-3">
                @forelse($enrollments as $enrollment)
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 p-5 dark:border-slate-700">
                        <div class="min-w-0"><p class="break-words text-sm font-black">{{ $enrollment->course?->title }}</p>
                            <p class="mt-2 text-xs text-slate-500 dark:text-slate-300">{{ $enrollment->course?->category?->name }} · {{ ucfirst($enrollment->status) }}</p></div>
                        @if($enrollment->status==='approved')
                            <a href="{{ route('account.enrollments.show',$enrollment) }}" class="rounded-xl bg-[#123864] px-4 py-2.5 text-xs font-bold text-white">Continue</a>
                        @else
                            <a href="{{ route('account.enrollments.index') }}" class="rounded-xl border border-[#d0ac68] px-4 py-2.5 text-xs font-bold text-[#aa7926]">View request</a>
                        @endif
                    </div>
                @empty<p class="text-sm text-slate-500">Your learning profile does not have enrolled courses yet.</p>@endforelse
                </div>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-white p-7 dark:border-slate-700 dark:bg-[#173655]">
                <h3 class="text-xl font-black">Your recent practice</h3>
                <div class="mt-5 space-y-3">
                    @forelse($attempts as $attempt)
                        <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                            <p class="text-sm font-bold">{{ $attempt->quiz?->title }}</p>
                            <p class="mt-2 text-xs text-slate-500 dark:text-slate-300">{{ $attempt->score_percent }}% practice score · {{ $attempt->passed?'Meets quiz threshold':'More study may help' }}</p>
                        </div>
                    @empty<p class="text-sm leading-7 text-slate-500">You have not submitted a practice quiz yet.</p>@endforelse
                </div>
                <a href="{{ route('account.practice.history') }}" class="mt-5 inline-block text-xs font-black text-[#a87c29]">My full practice history →</a>
            </div>
        </div>
    @else
        <div class="grid gap-5 md:grid-cols-3">
            <x-learning-card :href="route('login')" title="Sign in privately" description="Personalized suggestions rely only on your own authorized learning history." icon="lock"/>
            <x-learning-card :href="route('register')" title="Create a student account" description="Explore the actual enrollment request workflow." icon="user-check"/>
            <x-learning-card :href="route('categories.index')" title="Choose a subject" description="Browse approved course categories without signing in." icon="tags"/>
        </div>
    @endif
</x-learning-section>
<x-learning-section :title="$a['goals_heading']" eyebrow="Study action plan" tone="navy" subtitle="Recommendations are simple guidance, not required daily tasks or unverified AI-generated forecasts.">
    <div class="grid gap-5 md:grid-cols-3">
        <x-learning-card :href="route('categories.index')" title="Choose a learning focus" description="Identify a suitable public pathway from the available published categories." icon="compass"/>
        <x-learning-card :href="$student?route('account.enrollments.index'):route('login')" title="Review your enrollments" description="Check the status of your own learning requests and approved courses." icon="book-open"/>
        <x-learning-card :href="$student?route('account.practice.index'):route('login')" title="Reflect on progress" description="Use authorized lesson completion and practice feedback to decide what to review." icon="chart-line"/>
    </div>
</x-learning-section>
<x-learning-section title="How recommendations are selected" eyebrow="Transparent recommendation rules" tone="white">
    <div class="grid gap-5 md:grid-cols-2">
        <div class="premium-card rounded-3xl border border-slate-200 bg-[#f8fafc] p-7 dark:border-slate-700 dark:bg-[#193759]">
            <h3 class="text-lg font-black">Own learning interests</h3>
            <p class="mt-3 text-sm leading-8 text-slate-600 dark:text-slate-300">Once you enroll, the system suggests other currently published courses from the same approved subject categories. Previously requested courses are excluded from suggestions.</p>
        </div>
        <div class="premium-card rounded-3xl border border-slate-200 bg-[#f8fafc] p-7 dark:border-slate-700 dark:bg-[#193759]">
            <h3 class="text-lg font-black">No automated high-stakes profiling</h3>
            <p class="mt-3 text-sm leading-8 text-slate-600 dark:text-slate-300">No ranking by health, identity, hidden user information, protected characteristics or speculative academic performance. Results are not formal placement recommendations.</p>
        </div>
    </div>
</x-learning-section>
<x-learning-section :title="$a['cta_heading']" eyebrow="Your next step" tone="mist" :action-url="route('courses.index')" action-label="Explore approved courses" subtitle="Compare published course material before requesting your next learning opportunity."/>
@endsection
