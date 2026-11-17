<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\AssessmentAccess;
use App\Models\CourseEnrollment;
use App\Models\CourseExam;
use App\Models\ExamAttempt;
use App\Support\AssessmentQuestions;
use App\Support\CoursePricing;
use App\Support\LearningAccess;
use App\Support\WorkspaceNotifications;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

final class StudentExaminationController extends Controller
{
    private const AUDIO = [
        'audio/mpeg' => 'mp3', 'audio/mp4' => 'm4a', 'audio/x-m4a' => 'm4a',
        'audio/ogg' => 'ogg', 'application/ogg' => 'ogg', 'audio/wav' => 'wav',
        'audio/x-wav' => 'wav', 'audio/webm' => 'webm', 'video/webm' => 'webm',
    ];

    public function start(Request $request, CourseEnrollment $enrollment, CourseExam $exam): RedirectResponse
    {
        $course = LearningAccess::course($request->user(), $enrollment);
        abort_unless($exam->course_id === $course->id, 404);
        $attempt = $this->begin($request, $exam, $enrollment);

        return redirect()->route('account.exam-attempts.show', [$enrollment, $attempt]);
    }

    public function startStandalone(Request $request, CourseExam $exam): RedirectResponse
    {
        return redirect()->route('account.assessment-attempts.show', $this->begin($request, $exam));
    }

    private function begin(Request $request, CourseExam $exam, ?CourseEnrollment $enrollment = null): ExamAttempt
    {
        $studentId = $request->user()->id;

        return DB::transaction(function () use ($exam, $enrollment, $studentId): ExamAttempt {
            $exam = CourseExam::lockForUpdate()->findOrFail($exam->id);
            abort_unless($exam->published && $exam->course_id === $enrollment?->course_id, 404);
            $history = ExamAttempt::where('course_exam_id', $exam->id)->where('course_enrollment_id', $enrollment?->id);
            if ($enrollment) {
                abort_unless(CourseEnrollment::whereKey($enrollment->id)->where('status', 'approved')->lockForUpdate()->first(), 403);
            } else {
                $history->where('student_id', $studentId);
                $quote = CoursePricing::quote($exam->pricing);
                $access = AssessmentAccess::where('course_exam_id', $exam->id)->where('student_id', $studentId)
                    ->where('status', 'approved')->first();
                abort_unless($quote['model'] === 'free' || $access?->matches($quote), 403);
            }
            $active = (clone $history)->whereNull('submitted_at')->where('expires_at', '>', now())->first();
            if ($active) {
                return $active;
            }
            abort_if($history->count() >= $exam->attempt_limit, 409, 'All available attempts have been used.');
            $questions = $exam->questions;
            abort_if(empty($questions), 422, 'This assessment has no questions.');
            if ($exam->shuffle) {
                shuffle($questions);
            }

            return ExamAttempt::create([
                'course_exam_id' => $exam->id, 'course_enrollment_id' => $enrollment?->id,
                'student_id' => $enrollment ? null : $studentId,
                'snapshot' => [
                    'title' => $exam->title, 'instructions_html' => $exam->instructions_html, 'questions' => $questions,
                    'passing_percent' => $exam->passing_percent, 'gamified' => $exam->gamified,
                    'kind' => $exam->kind, 'live_feedback' => $exam->live_feedback,
                ],
                'expires_at' => now()->addMinutes($exam->time_limit),
            ]);
        });
    }

    public function show(Request $request, CourseEnrollment $enrollment, ExamAttempt $attempt): View
    {
        $this->authorizeAttempt($request, $attempt, $enrollment);

        return $this->attemptView($request, $attempt, $enrollment);
    }

    public function showStandalone(Request $request, ExamAttempt $attempt): View
    {
        $this->authorizeAttempt($request, $attempt);

        return $this->attemptView($request, $attempt);
    }

    private function attemptView(Request $request, ExamAttempt $attempt, ?CourseEnrollment $enrollment = null): View
    {
        if (!$attempt->submitted_at && $attempt->expires_at->isFuture()
            && in_array('speaking', array_column($attempt->snapshot['questions'], 'format'), true)) {
            $request->attributes->set('assessmentMicrophone', true);
        }

        return view('workspace.student.exam-attempt', compact('enrollment', 'attempt'));
    }

    public function submit(Request $request, CourseEnrollment $enrollment, ExamAttempt $attempt): RedirectResponse
    {
        $this->authorizeAttempt($request, $attempt, $enrollment);
        $this->finish($request, $attempt, $enrollment);

        return redirect()->route('account.exam-attempts.show', [$enrollment, $attempt]);
    }

    public function submitStandalone(Request $request, ExamAttempt $attempt): RedirectResponse
    {
        $this->authorizeAttempt($request, $attempt);
        $this->finish($request, $attempt);

        return redirect()->route('account.assessment-attempts.show', $attempt);
    }

    private function finish(Request $request, ExamAttempt $attempt, ?CourseEnrollment $enrollment = null): void
    {
        $data = $request->validate([
            'answers' => ['sometimes', 'array', 'max:200'], 'answers.*' => ['nullable', 'string', 'max:8000'],
            'recordings' => ['sometimes', 'array', 'max:50'], 'recordings.*' => ['file', 'max:10240'],
        ]);
        $files = [];
        try {
            DB::transaction(function () use ($attempt, $enrollment, $data, &$files): void {
                $locked = ExamAttempt::lockForUpdate()->findOrFail($attempt->id);
                $this->ensureOpen($locked, $enrollment);
                $answers = $locked->answers ?? [];
                foreach ($locked->snapshot['questions'] as $index => $question) {
                    if (array_key_exists($index, $answers)) {
                        continue;
                    }
                    $format = $question['format'] ?? 'choice';
                    if ($format === 'speaking') {
                        $recording = $data['recordings'][$index] ?? null;
                        if ($recording) {
                            $extension = self::AUDIO[$recording->getMimeType()] ?? null;
                            if (!$extension) {
                                throw \Illuminate\Validation\ValidationException::withMessages(["recordings.$index" => 'Upload an MP3, M4A, Ogg, WAV, or WebM recording.']);
                            }
                            $path = $recording->storeAs('assessment-responses', ((string) \Illuminate\Support\Str::uuid()).'.'.$extension, 'local');
                            abort_unless($path, 500, 'The recording could not be saved.');
                            $files[] = $answers[$index] = $path;
                        }
                    } elseif (isset($data['answers'][$index])) {
                        $answers[$index] = $data['answers'][$index];
                    }
                }
                $result = AssessmentQuestions::result($locked->snapshot, $answers);
                $locked->update($result + ['answers' => $answers, 'submitted_at' => now()]);
                if (array_filter($locked->snapshot['questions'], AssessmentQuestions::manual(...)) !== []) {
                    $route = ($locked->snapshot['kind'] ?? 'exam') === 'quiz' ? 'admin.quizzes-manager.edit' : 'admin.exams.edit';
                    WorkspaceNotifications::administrators('Assessment response ready to grade', $route, ['exam' => $locked->exam]);
                }
            });
        } catch (Throwable $error) {
            Storage::disk('local')->delete($files);
            throw $error;
        }
    }

    public function feedback(Request $request, ExamAttempt $attempt, ?CourseEnrollment $enrollment = null): JsonResponse
    {
        $this->authorizeAttempt($request, $attempt, $enrollment);
        $data = $request->validate(['question' => ['required', 'integer', 'min:0', 'max:199'], 'answer' => ['required', 'string', 'max:300']]);

        return DB::transaction(function () use ($attempt, $enrollment, $data): JsonResponse {
            $locked = ExamAttempt::lockForUpdate()->findOrFail($attempt->id);
            $this->ensureOpen($locked, $enrollment);
            $question = $locked->snapshot['questions'][$data['question']] ?? null;
            abort_unless(($locked->snapshot['live_feedback'] ?? false) && $question
                && AssessmentQuestions::scored($question) && !AssessmentQuestions::manual($question), 404);
            $answers = $locked->answers ?? [];
            if (!array_key_exists($data['question'], $answers)) {
                $answers[$data['question']] = $data['answer'];
                $locked->update(['answers' => $answers]);
            }

            return response()->json([
                'correct' => AssessmentQuestions::correct($question, $answers[$data['question']]),
                'feedback' => $question['feedback'] ?? null,
                'answer' => $answers[$data['question']],
            ]);
        });
    }

    public function courseFeedback(Request $request, CourseEnrollment $enrollment, ExamAttempt $attempt): JsonResponse
    {
        return $this->feedback($request, $attempt, $enrollment);
    }

    public function recording(Request $request, ExamAttempt $attempt, int $question): BinaryFileResponse
    {
        if (!$request->user()->isAdministrator()) {
            $this->authorizeAttempt($request, $attempt, $attempt->enrollment);
        }
        abort_unless($attempt->submitted_at && ($attempt->snapshot['questions'][$question]['format'] ?? null) === 'speaking', 404);
        $path = $attempt->answers[$question] ?? '';
        $types = ['mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'ogg' => 'audio/ogg', 'wav' => 'audio/wav', 'webm' => 'audio/webm'];
        $type = $types[pathinfo($path, PATHINFO_EXTENSION)] ?? null;
        abort_unless($type && str_starts_with($path, 'assessment-responses/') && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => $type, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function ensureOpen(ExamAttempt $attempt, ?CourseEnrollment $enrollment): void
    {
        abort_if($enrollment && $enrollment->fresh()?->status !== 'approved', 403);
        abort_if($attempt->submitted_at || !$attempt->expires_at->isFuture(), 409, 'This attempt has already ended.');
    }

    private function authorizeAttempt(Request $request, ExamAttempt $attempt, ?CourseEnrollment $enrollment = null): void
    {
        if ($enrollment) {
            LearningAccess::course($request->user(), $enrollment);
            abort_unless($attempt->course_enrollment_id === $enrollment->id, 404);
        } else {
            abort_unless($attempt->course_enrollment_id === null && $attempt->student_id === $request->user()->id, 404);
        }
    }
}
