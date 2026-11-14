@extends('layouts.public')
@section('title',\App\Support\Appearance::value('leaderboard_trophy','hero_heading'))
@section('content')
@php $a=\App\Support\Appearance::approved('leaderboard_trophy'); @endphp
<x-learning-hero :title="$a['hero_heading']" :subtitle="$a['hero_intro']" eyebrow="Leaderboard & Trophy / Personal progress" icon="award"
    :action-url="'#learning-milestones'" action-label="Explore verified milestones"
    :stats="[
        ['value'=>$student?number_format($approvedCount):'—','label'=>'Your approved enrollments','icon'=>'book-open'],
        ['value'=>$student?number_format($completeCount):'—','label'=>'Your marked-complete lessons','icon'=>'check-check'],
        ['value'=>$student?number_format($practiceCount):'—','label'=>'Your practice submissions','icon'=>'clipboard-check'],
        ['value'=>$student?number_format($passedCount):'—','label'=>'Your practice threshold passes','icon'=>'award'],
    ]"/>
<x-learning-section id="learning-milestones" :title="$a['milestone_heading']" eyebrow="Real personal learning records" tone="white" subtitle="No fabricated nationwide student leaderboard, medal rankings, XP balances or scholarships. Your private learning counts are visible only to you.">
    @if($student)
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($milestones as $milestone)
                <div class="premium-card flex min-h-60 flex-col rounded-3xl border p-7 shadow-sm {{ $milestone['earned']?'border-[#e1bb70] bg-[#fff8e9] dark:bg-[#45371f]':'border-slate-200 bg-[#f7f9fc] dark:border-slate-700 dark:bg-[#173657]' }}">
                    <span class="grid h-16 w-16 place-items-center rounded-full {{ $milestone['earned']?'bg-[#dba447] text-[#11335b]':'bg-slate-200 text-slate-500 dark:bg-[#36506c]' }}"><x-workspace-icon :name="$milestone['icon']" class="h-8 w-8"/></span>
                    <h3 class="mt-6 flex-1 text-lg font-black text-[#10345c] dark:text-white">{{ $milestone['label'] }}</h3>
                    <span class="mt-4 text-xs font-black {{ $milestone['earned']?'text-[#8f681f] dark:text-[#f6cd75]':'text-slate-500' }}">{{ $milestone['earned']?'Completed from your learning records':'Not yet recorded' }}</span>
                </div>
            @endforeach
        </div>
        <div class="mt-7 flex flex-wrap justify-end gap-3">
            <a href="{{ route('account.progress.index') }}" class="rounded-xl bg-[#123a64] px-5 py-3 text-xs font-black text-white">My lesson progress</a>
            <a href="{{ route('account.practice.history') }}" class="rounded-xl border border-[#d6ad63] px-5 py-3 text-xs font-black text-[#a97c26] dark:text-[#f5cb82]">My practice history</a>
        </div>
    @else
        <div class="rounded-3xl border border-[#e7d6b6] bg-[#fffaf1] p-9 dark:border-[#75603c] dark:bg-[#413522]">
            <h3 class="text-xl font-black text-[#10345e] dark:text-white">Your milestones are private</h3>
            <p class="mt-4 max-w-3xl text-sm leading-9 text-slate-700 dark:text-slate-200">Sign in to view genuine achievements based on your own approved enrollment, lesson completion and optional practice. Public leaderboard records are disabled until privacy, consent and safeguarding have been reviewed.</p>
            <a href="{{ route('login') }}" class="mt-6 inline-block rounded-xl bg-[#123965] px-6 py-3.5 text-sm font-black text-white">Sign in to view my learning</a>
        </div>
    @endif
</x-learning-section>
<x-learning-section :title="$a['method_heading']" eyebrow="Milestone criteria" tone="mist" subtitle="Milestones are informational personal achievements; they are not formal certificates, cash rewards or official academic rankings.">
    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['book-open','Enrollment milestone','An administrator has approved at least one of your actual course enrollment requests.'],
            ['check-check','Study milestone','You have self-marked at least one authorized lesson as complete. This is not instructor-verified mastery.'],
            ['clipboard-check','Practice milestone','You submitted at least one actual practice quiz in the authorized course.'],
            ['award','Passing practice milestone','A submitted quiz reached the course quiz passing threshold. It is not an external exam result.'],
        ] as [$icon,$name,$explain])
            <div class="premium-card rounded-3xl border border-slate-200 bg-white p-7 dark:border-slate-700 dark:bg-[#18395a]">
                <x-workspace-icon :name="$icon" class="h-10 w-10 text-[#ae7c26]"/>
                <h3 class="mt-5 text-lg font-black text-[#12345d] dark:text-white">{{ $name }}</h3>
                <p class="mt-3 text-sm leading-8 text-slate-600 dark:text-slate-300">{{ $explain }}</p>
            </div>
        @endforeach
    </div>
</x-learning-section>
<x-learning-section :title="$a['privacy_heading']" eyebrow="Student safeguarding" tone="navy" subtitle="A real public competition requires explicit adult consent, anti-fraud measures, child privacy protections and independently defined scoring policies. None are active yet.">
    <div class="grid gap-5 md:grid-cols-3">
        <x-learning-card :href="route('information.show','privacy')" title="No public learner rankings" description="Other learners' grades, names, scores and activity are not exposed on this page." icon="lock"/>
        <x-learning-card :href="route('account.overview')" title="Private student workspace" description="Open your own authorized learning dashboard when signed in as a student." icon="user-check"/>
        <x-learning-card :href="route('learning.exams')" title="Reviewed learning checks" description="Use optional course practice for feedback, never speculative competitive badges." icon="clipboard-check"/>
    </div>
</x-learning-section>
<x-learning-section title="Questions about badges & rankings" eyebrow="FAQs" tone="white">
    <div class="grid gap-4 lg:grid-cols-2">
        @foreach([
            ['Why is there no national leaderboard?','Public ranking of students requires an independent safeguarding and consent workflow. It is intentionally not enabled.'],
            ['Can I earn an official certification badge?','No official certificate issuance is live. These milestones are account-only learning indicators.'],
            ['Are scores calculated from other students?','No. Your account indicators come only from your own enrollment, completion and submitted practice records.'],
            ['Can milestones be exchanged for rewards?','No marketplace, payment, redemption or school scholarship rewards are enabled.'],
        ] as [$q,$answer])
            <details class="group rounded-2xl border border-slate-200 bg-[#f8fafc] p-6 dark:border-slate-700 dark:bg-[#183759]">
                <summary class="flex cursor-pointer items-center justify-between gap-3 text-sm font-black">{{ $q }}<x-workspace-icon name="chevron-down" class="h-5 w-5 shrink-0 group-open:rotate-180"/></summary>
                <p class="mt-4 text-sm leading-8 text-slate-600 dark:text-slate-300">{{ $answer }}</p>
            </details>
        @endforeach
    </div>
</x-learning-section>
<x-learning-section :title="$a['cta_heading']" eyebrow="Take your next learning step" tone="mist" :action-url="route('courses.index')" action-label="View approved courses" subtitle="Keep building your own record through course enrollment and meaningful practice."/>
@endsection
