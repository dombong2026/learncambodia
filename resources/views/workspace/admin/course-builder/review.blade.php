@if($course->review_note)
    <p class="builder-callout"><strong>Reviewer note</strong> {{ $course->review_note }}</p>
@endif
<div class="builder-media-grid">
    @if($course->featured_kind)
        <div class="builder-cover"><x-editorial-featured-media :entry="$course" /></div>
    @endif
    <div class="min-w-0 space-y-4">
        <div>
            <h3 class="break-words text-lg font-bold">{{ $course->title }}</h3>
            <p class="mt-1 break-words text-sm text-slate-600 dark:text-slate-300">{{ $course->summary }}</p>
        </div>
        <dl class="grid grid-cols-2 gap-3 text-sm">
            <div><dt class="font-semibold">Category</dt><dd>{{ $course->category?->name ?? '—' }}</dd></div>
            <div><dt class="font-semibold">CEFR</dt><dd>{{ $course->cefr_level ?? 'All levels' }}</dd></div>
            <div><dt class="font-semibold">Delivery mode</dt><dd>{{ ucfirst($course->delivery_mode) }}</dd></div>
            <div><dt class="font-semibold">Curriculum</dt><dd>{{ $course->modules->count() }} modules · {{ $course->modules->sum(fn ($module) => $module->lessons->count()) }} lessons</dd></div>
            <div><dt class="font-semibold">Practice quizzes</dt><dd>{{ $course->quizzes->count() }}</dd></div>
            <div><dt class="font-semibold">Featured media</dt><dd>{{ ucfirst($course->featured_kind ?? 'None') }}</dd></div>
        </dl>
    </div>
</div>
<section class="mt-7" aria-labelledby="builder-checklist">
    <h3 id="builder-checklist" class="text-lg font-extrabold">Academic review readiness</h3>
    <ul class="mt-3 space-y-2">
        @foreach($reviewChecklist as $label => $passed)
            @php $fixStep = match($label) {
                'Every module includes a lesson' => 'curriculum',
                'Every practice quiz includes a question' => 'quizzes',
                default => 'details',
            }; @endphp
            <li class="builder-row">
                <span class="{{ $passed ? 'builder-ready' : 'builder-warning' }}">{{ $passed ? 'Ready' : 'Needs attention' }}</span>
                <span class="min-w-0 flex-1 text-sm">{{ $label }}</span>
                @unless($passed)<a class="builder-back" href="{{ $stepUrl($fixStep) }}">{{ $steps[$fixStep][0] }} →</a>@endunless
            </li>
        @endforeach
    </ul>
</section>
@if($editable)
    <form id="course-submit-form" method="POST" action="{{ route('admin.course-builder.submit', $course) }}">@csrf
        <p class="mt-4 text-sm text-slate-600 dark:text-slate-300">A different administrator reviews this edition after submission.</p>
    </form>
@endif
