@extends('layouts.public')
@section('title',\App\Support\Appearance::value('upload_studio','hero_heading'))
@section('content')
@php $a=\App\Support\Appearance::approved('upload_studio'); @endphp
<x-learning-hero :title="$a['hero_heading']" :subtitle="$a['hero_intro']" eyebrow="Upload Studio / Instructor teaching" icon="book-plus"
    :action-url="'#studio-workspace'" action-label="Explore teaching tools"
    :stats="[
        ['value'=>$instructor?number_format($assignments->count()):'—','label'=>'Your active teaching assignments','icon'=>'school'],
        ['value'=>$instructor?number_format($homework->count()):'—','label'=>'Your recent homework records','icon'=>'notebook-pen'],
    ]"/>
<x-learning-section id="studio-workspace" :title="$a['courses_heading']" eyebrow="Authorized instructor workspace" tone="white" subtitle="Only instructor-owned assignments are shown here. Publishing and grading are restricted to existing verified teaching relationships.">
    @if($instructor)
        <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @forelse($assignments as $assignment)
                <x-learning-card :href="route('instructor.courses.show',$assignment->course)" :title="$assignment->course->title" :description="$assignment->course->summary" icon="school" eyebrow="Assigned teaching course" meta="Approved teaching role" action="Manage course"/>
            @empty
                <p class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-[#f8fafc] p-9 text-sm leading-8 text-slate-600 dark:border-slate-700 dark:bg-[#173758] dark:text-slate-300">
                    No active teaching assignments have been approved for your account.
                    <a href="{{ route('instructor.courses.index') }}" class="font-bold text-[#a77b30] underline">View teaching workspace</a>.
                </p>
            @endforelse
        </div>
    @else
        <div class="rounded-3xl border border-[#e1c48d] bg-[#fffaf0] p-9 dark:border-[#7a623e] dark:bg-[#403525]">
            <h3 class="text-xl font-black text-[#10335e] dark:text-white">Instructor authorization required</h3>
            <p class="mt-4 max-w-4xl text-sm leading-9 text-slate-600 dark:text-slate-300">The actual studio functions are limited to an administrator-managed instructor account with course assignments. Public registration does not grant teaching or publishing privileges.</p>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('login') }}" class="rounded-xl bg-[#123862] px-6 py-3 text-xs font-black text-white">Sign in</a>
                <a href="{{ route('information.show','contact') }}" class="rounded-xl border border-[#d7b46d] px-6 py-3 text-xs font-bold text-[#a57825] dark:text-[#f3c678]">Contact the school</a>
            </div>
        </div>
    @endif
</x-learning-section>
<x-learning-section :title="$a['tools_heading']" eyebrow="Actual authoring tools" tone="mist" subtitle="These actions open existing, permission-checked Laravel authoring and grading functions. The visual upload mockup is not represented as a live file uploader.">
    <div class="grid gap-5 md:grid-cols-3">
        <x-learning-card :href="$instructor?route('instructor.courses.index'):route('login')" title="Assigned course curriculum" description="Review only courses assigned to you. Academic course publication is separately controlled by administrators." icon="book-open" action="Open courses"/>
        <x-learning-card :href="$instructor?route('instructor.homework.index'):route('login')" title="Assignment editor" description="Create real text-based homework drafts, add a rubric and due date, and submit work for independent review." icon="notebook-pen" action="Open assignments"/>
        <x-learning-card :href="$instructor?route('instructor.grading.index'):route('login')" title="Grading and feedback" description="Review student work only within authorized teaching assignments, with grading access controls." icon="clipboard-check" action="Open grading"/>
    </div>
    @if($instructor)
        <div class="mt-10 rounded-3xl border border-slate-200 bg-white p-7 dark:border-slate-700 dark:bg-[#193858]">
            <div class="flex flex-wrap items-end justify-between gap-4"><h3 class="text-xl font-black">Your recently authored assignments</h3>
                <a href="{{ route('instructor.homework.index') }}" class="text-xs font-black text-[#af7b28]">View all →</a></div>
            <div class="mt-6 space-y-3">
                @forelse($homework as $item)
                    <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-slate-200 p-5 dark:border-slate-700">
                        <div class="min-w-0"><p class="text-sm font-black">{{ $item->title }}</p>
                            <p class="mt-2 text-xs text-slate-500 dark:text-slate-300">{{ $item->course?->title }} · {{ ucwords(str_replace('_',' ',$item->status)) }}</p></div>
                        @if(in_array($item->status,['draft','rejected'],true))
                            <a href="{{ route('instructor.homework.edit',$item) }}" class="rounded-xl bg-[#123965] px-5 py-2.5 text-xs font-black text-white">Edit draft</a>
                        @else
                            <a href="{{ route('instructor.homework.index') }}" class="rounded-xl border border-[#d5ab66] px-5 py-2.5 text-xs font-bold text-[#ad7e2c]">View status</a>
                        @endif
                    </div>
                @empty<p class="text-sm text-slate-500">No assignments have been authored under your instructor account yet.</p>@endforelse
            </div>
        </div>
    @endif
</x-learning-section>
<x-learning-section :title="$a['review_heading']" eyebrow="Secure academic workflow" tone="navy" subtitle="Instructors cannot grant themselves publication approval. Unauthorized file uploads, direct video publication, paid course flags and AI question generation are not enabled here.">
    <div class="grid gap-5 md:grid-cols-3">
        <x-learning-card :href="$instructor?route('instructor.courses.index'):route('login')" title="1. Verify teaching assignment" description="An administrator assigns an instructor to an eligible published course." icon="user-check"/>
        <x-learning-card :href="$instructor?route('instructor.homework.index'):route('login')" title="2. Create and submit a draft" description="Prepare real instructional text and assessment guidance with the existing instructor tools." icon="pen-square"/>
        <x-learning-card :href="route('information.show','about')" title="3. Independent academic review" description="Separate administrators review content before publication to students." icon="shield-check"/>
    </div>
</x-learning-section>
<x-learning-section title="Media intake and upload limits" eyebrow="Not activated" tone="white" subtitle="The attached illustration includes drag-and-drop, live uploads, storage meters and premium pricing. These are not available until a real secure media intake pipeline, scanner, storage and access review are implemented.">
    <div class="grid gap-5 md:grid-cols-2">
        <div class="rounded-3xl border border-dashed border-[#d4ac66] bg-[#fffaf0] p-8 dark:border-[#786240] dark:bg-[#403521]">
            <x-workspace-icon name="lock" class="h-10 w-10 text-[#af802e]"/>
            <h3 class="mt-5 text-lg font-black text-[#10365f] dark:text-white">File and video uploads are unavailable</h3>
            <p class="mt-3 text-sm leading-8 text-slate-600 dark:text-slate-300">No drop zone or Upload button is shown because this version does not have a rights-verified, scanned and private object-storage media workflow.</p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-[#f8fafc] p-8 dark:border-slate-700 dark:bg-[#173758]">
            <x-workspace-icon name="shield-check" class="h-10 w-10 text-[#ad7e29]"/>
            <h3 class="mt-5 text-lg font-black text-[#10365f] dark:text-white">Educational resources are reviewed</h3>
            <p class="mt-3 text-sm leading-8 text-slate-600 dark:text-slate-300">The existing homework publishing workflow requires independent administrator approval, safe text handling and role-based account permissions.</p>
        </div>
    </div>
</x-learning-section>
<x-learning-section title="Questions about the instructor studio" eyebrow="Frequently asked" tone="mist">
    <div class="grid gap-4 md:grid-cols-2">
        @foreach([
            ['Can a new registered student upload lessons?','No. Student accounts cannot obtain instructor or administrator privileges through registration.'],
            ['Can I create course homework?','Only an assigned instructor can create and submit coursework drafts, which require administrator review.'],
            ['Are video files supported?','No. A real upload, scanning, storage, copyright and transcoding workflow has not been independently accepted.'],
            ['Can I publish premium content or receive KHQR payments?','No. Billing, access plans, KHQR checkout and external payment integrations remain disabled.'],
        ] as [$q,$answer])
            <details class="group rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-[#183759]">
                <summary class="flex cursor-pointer items-center justify-between gap-3 text-sm font-black">{{ $q }}<x-workspace-icon name="chevron-down" class="h-5 w-5 shrink-0 group-open:rotate-180"/></summary><p class="mt-4 text-sm leading-8 text-slate-600 dark:text-slate-300">{{ $answer }}</p>
            </details>
        @endforeach
    </div>
</x-learning-section>
<x-learning-section :title="$a['cta_heading']" eyebrow="Verified instructors only" tone="navy" :action-url="$instructor?route('instructor.overview'):route('login')" :action-label="$instructor?'My teaching workspace':'Sign in to learn more'" subtitle="Use existing instructor tools without compromising course ownership or independent review."/>
@endsection
