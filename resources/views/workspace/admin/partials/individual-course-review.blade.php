@php($retryRejection = (string) old('review_course') === (string) $course->public_id && $errors->has('note'))
<dialog id="{{ $reviewDialog }}" class="review-dialog review-course-dialog"
        aria-labelledby="{{ $reviewDialog }}-title" data-course-review
        @if($retryRejection) data-retry-rejection @endif>
    <header class="review-course-dialog-header">
        <div>
            <p class="review-course-status"><span aria-hidden="true"></span> Pending review</p>
            <h2 id="{{ $reviewDialog }}-title">{{ $course->title }}</h2>
        </div>
        <button type="button" class="review-button review-close" data-course-review-close aria-label="Close review" autofocus>
            <x-workspace-icon name="close" class="review-icon" />
        </button>
    </header>

    <div class="review-course-dialog-body">
        <dl class="review-course-facts">
            <div><dt>Course ID</dt><dd>#{{ $course->public_id }}</dd></div>
            <div><dt>Submitted</dt><dd>{{ $course->submitted_at?->format('d M Y, H:i') ?? '—' }}</dd></div>
            <div><dt>Category</dt><dd>{{ $course->category?->name ?? '—' }}</dd></div>
            <div><dt>Price</dt><dd><x-course-price :course="$course" /></dd></div>
        </dl>

        @if(filled($course->summary))
            <p class="review-course-summary">{{ $course->summary }}</p>
        @endif
        @if($course->creator?->public_id)
            <p class="review-course-author">Created by account #{{ $course->creator->public_id }}</p>
        @endif

        <div class="review-course-links">
            <a href="{{ $curriculum }}" class="review-button">
                <x-workspace-icon name="book-open" class="review-icon" /> Inspect curriculum
            </a>
            <a href="{{ $history }}" class="review-button">
                <x-workspace-icon name="history" class="review-icon" /> History &amp; evidence
            </a>
        </div>

        <form method="POST" action="{{ route('admin.reviews.course', $course) }}" id="{{ $reviewDialog }}-reject-form" class="review-reject-form" data-course-review-rejection hidden>
            @csrf
            <input type="hidden" name="decision" value="reject">
            <input type="hidden" name="review_course" value="{{ $course->public_id }}">
            <label for="{{ $reviewDialog }}-note">Rejection reason</label>
            <textarea id="{{ $reviewDialog }}-note" name="note" rows="3" maxlength="500" required
                      @if($retryRejection) aria-invalid="true" aria-describedby="{{ $reviewDialog }}-error" @endif>{{ $retryRejection ? old('note') : '' }}</textarea>
            @if($retryRejection)
                <p class="review-danger" id="{{ $reviewDialog }}-error" role="alert">{{ $errors->first('note') }}</p>
            @endif
        </form>
    </div>

    <footer class="review-course-dialog-footer">
        <div class="review-course-decision" data-course-review-actions>
            <button type="button" class="review-button review-danger" data-course-review-reject>Reject</button>
            <form method="POST" action="{{ route('admin.reviews.course', $course) }}">
                @csrf
                <button type="submit" name="decision" value="approve" class="review-button review-primary">
                    <x-workspace-icon name="check" class="review-icon" /> Approve &amp; publish
                </button>
            </form>
        </div>
        <div class="review-course-decision" data-course-review-reject-actions hidden>
            <button type="button" class="review-button" data-course-review-back>Back</button>
            <button type="submit" class="review-button review-danger" form="{{ $reviewDialog }}-reject-form">Reject course</button>
        </div>
    </footer>
</dialog>
