<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\CourseEnrollment;
use App\Models\CourseExam;
use App\Models\ExamAttempt;
use App\Support\LearningAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class StudentExaminationController extends Controller
{
    public function start(Request $request, CourseEnrollment $enrollment, CourseExam $exam): RedirectResponse
    {
        $course = LearningAccess::course($request->user(), $enrollment);
        $attempt = DB::transaction(function () use ($course, $enrollment, $exam): ExamAttempt {
            $exam = CourseExam::lockForUpdate()->findOrFail($exam->id);
            abort_unless($exam->published && $exam->course_id === $course->id, 404);
            CourseEnrollment::whereKey($enrollment->id)->where('status', 'approved')->lockForUpdate()->firstOrFail();
            $history = ExamAttempt::where('course_exam_id', $exam->id)->where('course_enrollment_id', $enrollment->id);
            $active = (clone $history)->whereNull('submitted_at')->where('expires_at', '>', now())->first();
            if ($active) {
                return $active;
            }
            abort_if($history->count() >= $exam->attempt_limit, 409, 'All available attempts have been used.');
            $questions = $exam->questions;
            abort_if(empty($questions), 422, 'This exam has no scored activities.');
            if ($exam->shuffle) {
                shuffle($questions);
            }

            return ExamAttempt::create([
                'course_exam_id' => $exam->id, 'course_enrollment_id' => $enrollment->id,
                'snapshot' => [
                    'title' => $exam->title, 'instructions_html' => $exam->instructions_html, 'questions' => $questions,
                    'passing_percent' => $exam->passing_percent, 'gamified' => $exam->gamified,
                ],
                'expires_at' => now()->addMinutes($exam->time_limit),
            ]);
        });

        return redirect()->route('account.exam-attempts.show', [$enrollment, $attempt]);
    }

    public function show(Request $request, CourseEnrollment $enrollment, ExamAttempt $attempt): View
    {
        $this->authorizeAttempt($request, $enrollment, $attempt);

        return view('workspace.student.exam-attempt', compact('enrollment', 'attempt'));
    }

    public function submit(Request $request, CourseEnrollment $enrollment, ExamAttempt $attempt): RedirectResponse
    {
        $this->authorizeAttempt($request, $enrollment, $attempt);
        $answers = $request->validate(['answers' => ['sometimes', 'array', 'max:50'],
            'answers.*' => ['nullable', 'string', 'max:300']])['answers'] ?? [];
        DB::transaction(function () use ($attempt, $answers, $enrollment): void {
            $locked = ExamAttempt::lockForUpdate()->findOrFail($attempt->id);
            abort_unless($enrollment->fresh()?->status === 'approved', 403);
            abort_if($locked->submitted_at || !$locked->expires_at->isFuture(), 409, 'This attempt has already ended.');
            $score = 0;
            $total = 0;
            foreach ($locked->snapshot['questions'] as $index => $question) {
                $total += $question['points'];
                $answer = $answers[$index] ?? '';
                if (($question['format'] ?? 'choice') === 'short') {
                    $normalize = static fn (string $value): string => mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? ''));
                    $correct = $normalize($answer) === $normalize($question['correct']);
                } else {
                    $correct = $answer === $question['correct'];
                }
                if ($correct) {
                    $score += $question['points'];
                }
            }
            $percentage = intdiv($score * 100, $total);
            $locked->update([
                'answers' => $answers, 'score' => $percentage,
                'passed' => $percentage >= $locked->snapshot['passing_percent'], 'submitted_at' => now(),
            ]);
        });

        return redirect()->route('account.exam-attempts.show', [$enrollment, $attempt]);
    }

    private function authorizeAttempt(Request $request, CourseEnrollment $enrollment, ExamAttempt $attempt): void
    {
        LearningAccess::course($request->user(), $enrollment);
        abort_unless($attempt->course_enrollment_id === $enrollment->id, 404);
    }
}
