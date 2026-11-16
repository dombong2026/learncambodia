<div class="space-y-3">
    @forelse($course->quizzes as $quiz)
        <a href="{{ route('admin.course-builder.quizzes.edit', [$course, $quiz]) }}" class="builder-row">
            <span class="min-w-0 flex-1">
                <strong class="block break-words">{{ $quiz->title }}</strong>
                <span class="mt-1 block text-xs text-slate-600 dark:text-slate-300">{{ $quiz->questions_count }} {{ Str::plural('question', $quiz->questions_count) }} · {{ $quiz->passing_percent }}% passing score</span>
            </span>
            @unless($quiz->questions_count)<span class="builder-warning">Needs a question</span>@endunless
            <x-workspace-icon name="chevron-right" class="h-4 w-4 shrink-0" />
        </a>
    @empty
        <p class="rounded-xl border border-dashed border-slate-300 p-5 text-sm text-slate-600 dark:border-slate-700 dark:text-slate-300">No practice quizzes yet. You can continue without adding one.</p>
    @endforelse
</div>
@if($editable)
        <form data-builder-edit method="POST" action="{{ route('admin.course-builder.quizzes.store',$course) }}" class="mt-6 space-y-4 rounded-2xl border border-dashed border-blue-300 bg-blue-50 p-5 dark:border-blue-900 dark:bg-slate-950">@csrf
            <h3 class="text-base font-bold">Add a practice quiz</h3>
            <div class="grid gap-3 sm:grid-cols-2">
                <div><label class="mb-2 block text-xs font-bold" for="new-quiz-title">Title</label><input id="new-quiz-title" name="title" maxlength="180" required value="{{ old('title') }}" placeholder="Unit review quiz" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-900"></div>
                <div><label class="mb-2 block text-xs font-bold" for="new-quiz-pass">Passing score (%)</label><input id="new-quiz-pass" name="passing_percent" type="number" min="0" max="100" value="{{ old('passing_percent', 70) }}" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-900"></div>
            </div>
            <div><label class="mb-2 block text-xs font-bold" for="new-quiz-instructions">Instructions (optional)</label><textarea id="new-quiz-instructions" name="instructions" maxlength="1000" rows="2" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-900">{{ old('instructions') }}</textarea></div>
            <input type="hidden" name="position" value="{{ (int) $course->quizzes->max('position') + 1 }}">
            <div class="flex justify-end"><button type="submit" class="rounded-full bg-blue-700 px-6 py-3 text-sm font-bold text-white">Create practice quiz</button></div>
        </form>
@endif
