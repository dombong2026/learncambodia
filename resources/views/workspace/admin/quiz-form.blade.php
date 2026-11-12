@extends('layouts.workspace')
@section('title', 'Practice Quiz Builder')
@section('heading', 'Practice Quiz Builder')
@section('description', 'Author four-option, single-answer practice questions. These are educational exercises, not official language tests or certifications.')
@section('content')
<a href="{{ route('admin.course-builder.edit', $course) }}" class="inline-block rounded-full border border-slate-300 px-5 py-2.5 text-sm font-semibold dark:border-slate-700">← Return to course</a>
<div class="mt-6 max-w-5xl space-y-6">
    <section class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-xl font-extrabold">Quiz settings</h2>
        <p class="mt-2 text-xs text-slate-500">Course: {{ $course->title }} · {{ $editable ? 'Editable draft' : 'Locked curriculum' }}</p>
        <form method="POST" action="{{ route('admin.course-builder.quizzes.update', [$course, $quiz]) }}" class="mt-6 space-y-4">@csrf @method('PATCH')
            <fieldset @disabled(!$editable) class="space-y-4">
                <div><label class="mb-2 block text-sm font-bold" for="quiz-title">Quiz title</label><input id="quiz-title" name="title" required maxlength="180" value="{{ old('title',$quiz->title) }}" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950"></div>
                <div><label class="mb-2 block text-sm font-bold" for="quiz-instructions">Instructions</label><textarea id="quiz-instructions" name="instructions" maxlength="1000" rows="3" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">{{ old('instructions',$quiz->instructions) }}</textarea></div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label for="quiz-order" class="mb-2 block text-sm font-bold">Display order</label><input id="quiz-order" name="position" type="number" min="1" max="999" required value="{{ old('position',$quiz->position) }}" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950"></div>
                    <div><label for="quiz-pass" class="mb-2 block text-sm font-bold">Pass threshold (%)</label><input id="quiz-pass" name="passing_percent" type="number" min="0" max="100" required value="{{ old('passing_percent',$quiz->passing_percent) }}" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950"></div>
                </div>
                @if($editable)<div class="flex justify-end"><button type="submit" class="rounded-full bg-blue-700 px-5 py-3 text-sm font-bold text-white">Save quiz</button></div>@endif
            </fieldset>
        </form>
    </section>
    <h2 class="text-xl font-extrabold">Questions ({{ $quiz->questions->count() }} of 50)</h2>
    @foreach($quiz->questions as $question)
    <details class="rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <summary class="cursor-pointer text-sm font-bold">Question {{ $question->position }} · {{ \Illuminate\Support\Str::limit($question->prompt, 100) }}</summary>
        <form method="POST" action="{{ route('admin.course-builder.quizzes.questions.update', [$course, $quiz, $question]) }}" class="mt-5 space-y-4">@csrf @method('PATCH')
            <fieldset @disabled(!$editable) class="space-y-3">
                <div><label class="block text-sm font-semibold" for="question-prompt-{{ $question->id }}">Question</label><textarea id="question-prompt-{{ $question->id }}" name="prompt" required maxlength="1500" rows="3" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">{{ $question->prompt }}</textarea></div>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach(['a','b','c','d'] as $letter)
                        @php $field = 'option_'.$letter; @endphp
                        <div><label for="option-{{ $letter }}-{{ $question->id }}" class="mb-2 block text-sm font-semibold">Option {{ strtoupper($letter) }}</label><input id="option-{{ $letter }}-{{ $question->id }}" name="{{ $field }}" value="{{ $question->$field }}" maxlength="500" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950"></div>
                    @endforeach
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div><label class="mb-2 block text-sm font-semibold" for="correct-{{ $question->id }}">Correct option</label><select id="correct-{{ $question->id }}" name="correct_option" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">@foreach(['A','B','C','D'] as $letter)<option value="{{ $letter }}" @selected($question->correct_option===$letter)>{{ $letter }}</option>@endforeach</select></div>
                    <div><label for="position-{{ $question->id }}" class="mb-2 block text-sm font-semibold">Order</label><input id="position-{{ $question->id }}" type="number" name="position" min="1" max="999" required value="{{ $question->position }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950"></div>
                </div>
                <div><label class="mb-2 block text-sm font-semibold" for="explanation-{{ $question->id }}">Post-submission explanation (optional)</label><textarea id="explanation-{{ $question->id }}" name="explanation" maxlength="2000" rows="3" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">{{ $question->explanation }}</textarea></div>
                @if($editable)<div class="flex justify-end"><button type="submit" class="rounded-full border border-blue-500 px-5 py-2.5 text-sm font-bold text-blue-700 dark:text-blue-400">Save question</button></div>@endif
            </fieldset>
        </form>
        @if($editable)
            <form method="POST" action="{{ route('admin.course-builder.quizzes.questions.destroy', [$course, $quiz, $question]) }}" class="mt-3 flex justify-end">@csrf @method('DELETE')<button type="submit" class="text-xs font-bold text-red-600">Delete draft question</button></form>
        @endif
    </details>
    @endforeach
    @if($editable)
    <section class="rounded-3xl border border-dashed border-blue-300 bg-blue-50 p-7 dark:border-blue-900 dark:bg-slate-900">
        <h2 class="text-lg font-extrabold">Add practice question</h2>
        <form method="POST" action="{{ route('admin.course-builder.quizzes.questions.store',[$course,$quiz]) }}" class="mt-5 space-y-4">@csrf
            <div><label for="new-prompt" class="mb-2 block text-sm font-bold">Question prompt</label><textarea id="new-prompt" name="prompt" rows="3" maxlength="1500" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950"></textarea></div>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach(['A','B','C','D'] as $option)
                <div><label for="new-option-{{ $option }}" class="mb-2 block text-sm font-semibold">Option {{ $option }}</label><input id="new-option-{{ $option }}" name="option_{{ strtolower($option) }}" maxlength="500" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950"></div>
                @endforeach
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div><label class="mb-2 block text-sm font-semibold" for="new-correct">Correct option</label><select id="new-correct" name="correct_option" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">@foreach(['A','B','C','D'] as $letter)<option value="{{ $letter }}">{{ $letter }}</option>@endforeach</select></div>
                <div><label class="mb-2 block text-sm font-semibold" for="new-position">Order</label><input id="new-position" name="position" type="number" min="1" max="999" value="{{ $quiz->questions->count()+1 }}" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950"></div>
            </div>
            <div><label for="new-explanation" class="mb-2 block text-sm font-semibold">Explanation after submission (optional)</label><textarea id="new-explanation" name="explanation" maxlength="2000" rows="3" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950"></textarea></div>
            <div class="flex justify-end"><button type="submit" class="rounded-full bg-blue-700 px-6 py-3 text-sm font-bold text-white">Add question</button></div>
        </form>
    </section>
    <form method="POST" action="{{ route('admin.course-builder.quizzes.destroy',[$course,$quiz]) }}" class="flex justify-end">@csrf @method('DELETE')<button type="submit" class="rounded-full border border-red-300 px-5 py-3 text-sm font-bold text-red-700 dark:border-red-900">Delete draft quiz</button></form>
    @else
        <p class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm font-semibold text-amber-700 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300">Quiz locked under curriculum review or after publication.</p>
    @endif
</div>
@endsection
