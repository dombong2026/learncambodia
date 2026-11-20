@if($batch = session('course_review_batch'))
    <div class="review-result" role="status">
        <p><strong>{{ $batch['published'] }} {{ Str::plural('course', $batch['published']) }} published.</strong>
            @if($batch['skipped'] !== [])
                {{ count($batch['skipped']) }} skipped.
            @endif
        </p>
        @if($batch['skipped'] !== [])
            <ul>
                @foreach($batch['skipped'] as $item)
                    <li><strong>{{ $item['course'] }}</strong> <span>{{ $item['reason'] }}</span></li>
                @endforeach
            </ul>
        @endif
    </div>
@endif

@if($errors->any())
    <div class="review-result" role="alert">
        @foreach($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </div>
@endif

<section class="review-panel" aria-labelledby="pending-courses-heading" data-review-queue>
    <div class="review-toolbar">
        <div>
            <h2 id="pending-courses-heading">Pending courses</h2>
            <p>{{ $courses->total() }} awaiting review</p>
        </div>
        @if($courses->isNotEmpty())
            <div class="review-selection" hidden data-review-selection>
                <button class="review-button review-primary" type="button" data-review-confirm disabled>
                    <x-workspace-icon name="check" class="review-icon" />
                    Approve &amp; publish
                    <span class="review-counter" data-review-count>0</span>
                </button>
            </div>
        @endif
    </div>

    @if($courses->isNotEmpty())
        <div class="review-table-header">
            <label class="review-select-all" hidden data-review-selection>
                <input type="checkbox" data-review-select-all>
                <span>Select this page</span>
            </label>
            <span class="review-submitted-heading">Submitted</span>
        </div>
        <ul class="review-list">
            @foreach($courses as $course)
                @php
                    $curriculum = route('admin.course-builder.edit', ['course' => $course, 'step' => 'curriculum', 'lang' => app()->getLocale()]);
                    $history = route('admin.course-builder.history.index', ['course' => $course, 'lang' => app()->getLocale()]);
                @endphp
                <li class="review-course" data-review-course>
                    <label class="review-row-select" hidden data-review-selection>
                        <input type="checkbox" name="courses[]" form="approve-selected-courses" value="{{ $course->public_id }}" data-review-choice>
                        <span class="sr-only">Select {{ $course->title }}</span>
                    </label>
                    <div class="review-identity">
                        <a class="review-title" href="{{ $curriculum }}" data-review-title>{{ $course->title }}</a>
                        <div class="review-meta">
                            <span>#{{ $course->public_id }}</span>
                            @if($course->category)
                                <span>{{ $course->category->name }}</span>
                            @endif
                            <span><x-course-price :course="$course" /></span>
                        </div>
                    </div>
                    <div class="review-submitted">
                        @if($course->submitted_at)
                            <time datetime="{{ $course->submitted_at->toIso8601String() }}">
                                {{ $course->submitted_at->format('d M Y') }}
                                <span>{{ $course->submitted_at->format('H:i') }}</span>
                            </time>
                        @else
                            <span>—</span>
                        @endif
                    </div>
                    @php($reviewDialog = 'course-review-'.$course->public_id)
                    <a class="review-button review-open-course"
                       href="{{ route('admin.course-builder.edit', ['course' => $course, 'step' => 'review', 'lang' => app()->getLocale()]) }}"
                       data-course-review-trigger aria-haspopup="dialog" aria-controls="{{ $reviewDialog }}">
                        Review <span class="sr-only">{{ $course->title }}</span>
                        <x-workspace-icon name="arrow-up-right" class="review-icon" />
                    </a>
                    @include('workspace.admin.partials.individual-course-review')
                </li>
            @endforeach
        </ul>

        <dialog class="review-dialog" aria-labelledby="review-publish-title" data-review-dialog>
            <form method="POST" id="approve-selected-courses" action="{{ route('admin.reviews.courses.approve-selected', ['lang' => app()->getLocale()]) }}">
                @csrf
                <h2 id="review-publish-title">Publish selected courses</h2>
                <p>Courses that pass the review checks will appear in the public catalog.</p>
                <ul data-review-publish-list></ul>
                <div class="review-dialog-actions">
                    <button type="button" class="review-button" data-review-cancel autofocus>Cancel</button>
                    <button type="submit" class="review-button review-primary" data-review-publish>Publish courses</button>
                </div>
            </form>
        </dialog>
    @else
        <p class="review-empty">No courses awaiting review on this page.</p>
    @endif
</section>

@if($courses->hasPages())
    <div class="mt-4">{{ $courses->appends(['focus' => 'courses', 'lang' => app()->getLocale()])->links() }}</div>
@endif
