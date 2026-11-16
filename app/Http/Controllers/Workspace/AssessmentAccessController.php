<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\AssessmentAccess;
use App\Models\CourseExam;
use App\Support\CoursePricing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class AssessmentAccessController extends Controller
{
    public function catalog(Request $request): View
    {
        $kind = $request->routeIs('assessments.quizzes') ? 'quiz' : 'exam';
        $items = CourseExam::whereNull('course_id')->where('kind', $kind)->where('published', true)
            ->with('type')->orderBy('title')->paginate(12);

        return view('learning.standalone-assessments', compact('items', 'kind'));
    }

    public function show(Request $request, CourseExam $exam): View
    {
        abort_unless($exam->published && $exam->course_id === null, 404);
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
            abort_unless($exam->published && $exam->course_id === null, 404);
            $quote = CoursePricing::quote($exam->pricing);
            abort_if($quote['model'] === 'free', 422);
            abort_unless(hash_equals(json_encode($quote, JSON_THROW_ON_ERROR), $data['pricing_quote']),
                409, 'The price has changed. Reload the assessment and review the current price.');

            $record = AssessmentAccess::firstOrNew([
                'course_exam_id' => $exam->id, 'student_id' => $request->user()->id,
            ]);
            if (!$record->exists || $record->status === 'declined' || !$record->matches($quote)) {
                $record->fill(['quote' => $quote, 'status' => 'pending', 'reference' => null, 'reviewed_by' => null])->save();
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
        $updated = AssessmentAccess::whereKey($access->id)->where('status', 'pending')->update([
            'status' => $data['decision'], 'reference' => $data['reference'] ?? null,
            'reviewed_by' => $request->user()->id,
        ]);
        abort_unless($updated === 1, 409, 'This request already has a decision.');

        return back()->with('status', 'Access decision saved.');
    }
}
