<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\AssessmentAccess;
use App\Models\CourseExam;
use App\Models\CourseEnrollment;
use App\Models\ExamAttempt;
use App\Models\ExamType;
use App\Support\AssessmentCatalog;
use App\Support\CoursePricing;
use App\Support\LearningTable;
use App\Support\WorkspaceNotifications;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class AssessmentAccessController extends Controller
{
    public function catalog(Request $request): View
    {
        $kind = $request->routeIs('assessments.quizzes') ? 'quiz' : 'exam';
        $filters = array_filter($request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'level' => ['nullable', Rule::in(AssessmentCatalog::LEVELS)],
            'skill' => ['nullable', Rule::in(array_keys(AssessmentCatalog::SKILLS))],
            'type' => ['nullable', 'integer', 'min:1'],
            'duration' => ['nullable', Rule::in(AssessmentCatalog::DURATIONS)],
        ]), fn ($value) => filled($value));
        $published = AssessmentCatalog::published($kind);
        $search = trim($filters['q'] ?? '');
        $types = ExamType::query()->whereIn('id', (clone $published)->select('exam_type_id'))
            ->orderBy('name')->orderBy('id')->get(['id', 'name']);

        $items = $published
            ->when($search !== '', fn (Builder $query) => $query->where(
                fn (Builder $match) => $match->whereLike('title', '%'.$search.'%')
                    ->orWhereHas('type', fn (Builder $type) => $type->whereLike('name', '%'.$search.'%')),
            ))
            ->when($filters['level'] ?? null, AssessmentCatalog::level(...))
            ->when($filters['skill'] ?? null, AssessmentCatalog::skill(...))
            ->when($filters['type'] ?? null, fn (Builder $query, $type) => $query->where('exam_type_id', $type))
            ->when($filters['duration'] ?? null, fn (Builder $query, $duration) => $query->where('time_limit', '<=', $duration))
            ->with(['type', 'featuredMedia'])->orderBy('title')->orderBy('id')->paginate(12)
            ->appends(['lang' => app()->getLocale()] + $filters)->fragment($kind === 'quiz' ? 'quiz-results' : 'exam-results');

        return view('learning.standalone-assessments', compact('items', 'kind', 'filters', 'types'));
    }

    public function assigned(Request $request): View
    {
        $student = $request->user();
        $assignments = AssessmentAccess::where('student_id', $student->id)->whereNotNull('assigned_at')->where('status', 'approved');
        $query = CourseExam::whereIn('id', $assignments->select('course_exam_id'))
            ->with('type', 'course.category')->orderBy('title')->orderBy('id');
        $exams = LearningTable::paginate(LearningTable::assessments($query, LearningTable::search($request)), $request);
        $enrollments = CourseEnrollment::where('student_id', $student->id)->where('status', 'approved')
            ->whereIn('course_id', $exams->pluck('course_id')->filter())
            ->whereHas('course', fn ($course) => $course->published())->get()->keyBy('course_id');

        $specializedResults = ExamAttempt::where('student_id', $student->id)->whereNull('course_enrollment_id')
            ->where('snapshot->viewer_mode', 'specialized')->whereNotNull('submitted_at')
            ->latest('submitted_at')->paginate(12, ['*'], 'results_page')->withQueryString();

        return view('workspace.student.assigned-assessments', [
            'role' => 'student', 'exams' => $exams, 'enrollments' => $enrollments, 'specializedResults' => $specializedResults,
        ]);
    }

    public function show(Request $request, CourseExam $exam, ?string $kind = null): View|RedirectResponse
    {
        abort_unless($exam->published && $exam->course_id === null && ($kind === null || $exam->kind === $kind), 404);
        if ($exam->isSpecialized()) {
            return to_route('specialized-exams.show', ['exam' => $exam, 'lang' => app()->getLocale()]);
        }
        $exam->load('type', 'featuredMedia');
        $student = $request->user();
        $access = $history = null;
        if ($student?->role === 'student') {
            $access = AssessmentAccess::where('course_exam_id', $exam->id)->where('student_id', $student->id)->first();
            $history = \App\Models\ExamAttempt::where('course_exam_id', $exam->id)
                ->whereNull('course_enrollment_id')->where('student_id', $student->id)->latest('id')->get();
        }
        $quote = CoursePricing::quote($exam->pricing);

        return view('learning.standalone-assessment', compact('exam', 'quote', 'access', 'history'));
    }

    public function requestAccess(Request $request, CourseExam $exam): RedirectResponse
    {
        $data = $request->validate(['pricing_quote' => ['required', 'string', 'max:1000']]);
        DB::transaction(function () use ($request, $exam, $data): void {
            $exam = CourseExam::lockForUpdate()->findOrFail($exam->id);
            abort_unless($exam->published && $exam->course_id === null && !$exam->isSpecialized(), 404);
            $quote = CoursePricing::quote($exam->pricing);
            abort_if($quote['model'] === 'free', 422);
            abort_unless(hash_equals(json_encode($quote, JSON_THROW_ON_ERROR), $data['pricing_quote']),
                409, 'The price has changed. Reload the assessment and review the current price.');

            $record = AssessmentAccess::firstOrNew([
                'course_exam_id' => $exam->id, 'student_id' => $request->user()->id,
            ]);
            if (!$record->exists || $record->status === 'declined' || !$record->matches($quote)) {
                $record->fill(['quote' => $quote, 'status' => 'pending', 'reference' => null, 'reviewed_by' => null])->save();
                WorkspaceNotifications::administrators('New assessment access request', 'admin.exams.edit', ['exam' => $exam]);
            }
        });

        return back()->with('status', 'Access request received.');
    }

    public function review(Request $request, AssessmentAccess $access): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required','in:approved,declined'],
            'reference' => ['required_if:decision,approved','nullable','string','max:160'],
        ]);
        DB::transaction(function () use ($request, $access, $data): void {
            $updated = AssessmentAccess::whereKey($access->id)->where('status', 'pending')->update([
                'status' => $data['decision'], 'reference' => $data['reference'] ?? null,
                'reviewed_by' => $request->user()->id,
            ]);
            abort_unless($updated === 1, 409, 'This request already has a decision.');
            WorkspaceNotifications::send($access->student_id, 'Assessment access '.$data['decision'], 'public.exams.show',
                ['exam' => $access->exam]);
        });

        return back()->with('status', 'Access decision saved.');
    }
}
