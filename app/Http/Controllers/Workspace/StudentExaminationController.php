<?php

namespace App\Http\Controllers\Workspace;

use App\Support\AdminAccess;

use App\Http\Controllers\Controller;
use App\Models\AssessmentAccess;
use App\Models\CourseEnrollment;
use App\Models\CourseExam;
use App\Models\ExamAttempt;
use App\Models\IssuedCertificate;
use App\Support\AssessmentQuestions;
use App\Support\CertificateAwards;
use App\Support\CoursePricing;
use App\Support\LearningAccess;
use App\Support\WorkspaceNotifications;
use App\Support\ManagedFiles;
use App\Support\SpecializedExamAttempts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
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
        $lesson = LearningAccess::sourceLesson($request, $course);
        $attempt = $this->begin($request, $exam, $enrollment);

        return redirect()->route('account.exam-attempts.show', [$enrollment, $attempt] + ($lesson ? ['from_lesson' => $lesson->id] : []));
    }

    public function startStandalone(Request $request, CourseExam $exam): RedirectResponse
    {
        if ($exam->isSpecialized()) {
            return $this->startSpecialized($request, $exam);
        }
        $attempt = $this->begin($request, $exam);

        return to_route($attempt->resultRoute(), $attempt);
    }

    public function specialized(Request $request, CourseExam $exam): View
    {
        abort_unless($exam->published && $exam->isSpecialized(), 404);
        $participant = SpecializedExamAttempts::participant($request->user(), $exam->kind);
        $browser = SpecializedExamAttempts::browser($request, !$participant);
        $history = SpecializedExamAttempts::visible(ExamAttempt::where('course_exam_id', $exam->id), $participant, $browser)
            ->latest('id')->get();
        $active = $history->first(fn (ExamAttempt $item) => !$item->submitted_at && $item->expires_at->isFuture());

        return view('learning.specialized-exam', compact('exam', 'history', 'active'));
    }

    public function startSpecialized(Request $request, CourseExam $exam): RedirectResponse
    {
        abort_unless($exam->published && $exam->isSpecialized(), 404);
        $participant = SpecializedExamAttempts::participant($request->user(), $exam->kind);
        $browser = SpecializedExamAttempts::browser($request);
        if (!$participant && $browser === null) {
            return to_route('specialized-exams.show', $exam)
                ->with('status', __($exam->kind === 'quiz' ? 'specialized_exam.quiz_cookies' : 'specialized_exam.cookies'));
        }
        $attempt = $this->begin($request, $exam, specialized: true, browser: $browser);

        return to_route($attempt->resultRoute(), $attempt);
    }

    private function begin(Request $request, CourseExam $exam, ?CourseEnrollment $enrollment = null,
        bool $specialized = false, ?string $browser = null): ExamAttempt
    {
        return DB::transaction(function () use ($request, $exam, $enrollment, $specialized, $browser): ExamAttempt {
            $exam = CourseExam::lockForUpdate()->findOrFail($exam->id);
            abort_unless($exam->published && $exam->course_id === $enrollment?->course_id
                && $exam->isSpecialized() === $specialized, 404);
            $participant = SpecializedExamAttempts::participant($request->user(), $specialized ? $exam->kind : 'exam');
            $studentId = $participant?->id;
            $history = ExamAttempt::where('course_exam_id', $exam->id)->where('course_enrollment_id', $enrollment?->id);
            if ($enrollment) {
                abort_unless(CourseEnrollment::whereKey($enrollment->id)->where('status', 'approved')->lockForUpdate()->first(), 403);
            } elseif ($specialized) {
                abort_unless($participant || $browser !== null, 403);
                SpecializedExamAttempts::visible($history, $participant, $browser);
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
                'guest_browser' => $specialized && !$participant ? $browser : null,
                'snapshot' => [
                    'title' => $exam->title, 'instructions_html' => $exam->instructions_html, 'questions' => $questions,
                    'passing_percent' => $exam->passing_percent, 'gamified' => $exam->gamified,
                    'kind' => $exam->kind, 'live_feedback' => $exam->live_feedback,
                    'viewer_mode' => $specialized ? 'specialized' : 'default',
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

    public function showStandalone(Request $request, ExamAttempt $attempt): View|RedirectResponse
    {
        $this->authorizeAttempt($request, $attempt);

        return $attempt->isSpecialized() ? to_route($attempt->resultRoute(), $attempt) : $this->attemptView($request, $attempt);
    }

    public function showSpecialized(Request $request, ExamAttempt $attempt): View
    {
        $this->authorizeAttempt($request, $attempt);

        $exam = $attempt->exam()->withTrashed()->firstOrFail();
        $student = SpecializedExamAttempts::student($request->user());
        $participant = SpecializedExamAttempts::participant($request->user(), $attempt->snapshot['kind'] ?? 'exam');
        $owned = $participant && $attempt->student_id === $participant->id;
        $certificate = $owned && $student && $attempt->passed
            ? IssuedCertificate::where('award_key', CertificateAwards::assessmentKey($attempt, $student->id))->first()
            : null;
        $this->enableMicrophone($request, $attempt);

        return view('learning.specialized-exam', compact('exam', 'attempt', 'student', 'owned', 'certificate'));
    }

    public function saveSpecializedEmail(Request $request, ExamAttempt $attempt): RedirectResponse
    {
        $this->authorizeAttempt($request, $attempt);
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        DB::transaction(function () use ($request, $attempt, $data): void {
            $locked = ExamAttempt::lockForUpdate()->findOrFail($attempt->id);
            abort_unless($locked->submitted_at && SpecializedExamAttempts::ownsBrowser($request, $locked), 404);
            $locked->forceFill(['guest_email' => SpecializedExamAttempts::emailKey($data['email'])])->save();
        });

        return to_route($attempt->resultRoute(), $attempt)->with('status', __('specialized_exam.email_saved'));
    }

    public function claimSpecialized(Request $request, ExamAttempt $attempt): RedirectResponse
    {
        $student = SpecializedExamAttempts::student($request->user());
        abort_unless($student, 403);
        DB::transaction(function () use ($request, $attempt, $student): void {
            $locked = ExamAttempt::lockForUpdate()->findOrFail($attempt->id);
            abort_unless($locked->submitted_at && SpecializedExamAttempts::owns($request, $locked), 404);
            if ($locked->student_id === $student->id) {
                return;
            }
            if ($locked->guest_email !== null && $locked->guest_email !== SpecializedExamAttempts::emailKey($student->email)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['email' => __('specialized_exam.email_mismatch')]);
            }
            SpecializedExamAttempts::claim($locked, $student);
        });

        return to_route($attempt->resultRoute(), $attempt)->with('status', __('specialized_exam.account_saved'));
    }

    private function attemptView(Request $request, ExamAttempt $attempt, ?CourseEnrollment $enrollment = null): View
    {
        $this->enableMicrophone($request, $attempt);

        $focus = [];
        if ($enrollment && $request->has('from_lesson')) {
            $course = LearningAccess::course($request->user(), $enrollment);
            $lesson = LearningAccess::sourceLesson($request, $course);
            $completedIds = LearningAccess::curriculumProgress($course, $enrollment);
            $focus = compact('course', 'lesson', 'completedIds');
        }

        $certificate = $attempt->passed === true
            ? IssuedCertificate::where('award_key', CertificateAwards::assessmentKey($attempt, $request->user()->id))->first()
            : null;

        return view('workspace.student.exam-attempt', compact('enrollment', 'attempt', 'certificate') + $focus);
    }

    public function submit(Request $request, CourseEnrollment $enrollment, ExamAttempt $attempt): RedirectResponse
    {
        $this->authorizeAttempt($request, $attempt, $enrollment);
        $lesson = $request->has('from_lesson') ? LearningAccess::sourceLesson($request, $enrollment->course) : null;
        $this->finish($request, $attempt, $enrollment);

        return redirect()->route('account.exam-attempts.show', [$enrollment, $attempt] + ($lesson ? ['from_lesson' => $lesson->id] : []));
    }

    public function submitStandalone(Request $request, ExamAttempt $attempt): RedirectResponse
    {
        $this->authorizeAttempt($request, $attempt);
        $this->finish($request, $attempt);

        return to_route($attempt->resultRoute(), $attempt);
    }

    private function enableMicrophone(Request $request, ExamAttempt $attempt): void
    {
        if (!$attempt->submitted_at && $attempt->expires_at->isFuture()
            && in_array('speaking', array_column($attempt->snapshot['questions'], 'format'), true)) {
            $request->attributes->set('assessmentMicrophone', true);
        }
    }

    private function finish(Request $request, ExamAttempt $attempt, ?CourseEnrollment $enrollment = null): void
    {
        $data = $request->validate([
            'answers' => ['sometimes', 'array', 'max:200'], 'answers.*' => ['nullable', 'string', 'max:8000'],
            'recordings' => ['sometimes', 'array', 'max:50'], 'recordings.*' => ['file', 'max:10240'],
        ]);
        $files = [];
        try {
            DB::transaction(function () use ($request, $attempt, $enrollment, $data, &$files): void {
                if ($enrollment) {
                    $enrollment = CourseEnrollment::lockForUpdate()->findOrFail($enrollment->id);
                }
                $locked = ExamAttempt::lockForUpdate()->findOrFail($attempt->id);
                $this->authorizeAttempt($request, $locked, $enrollment);
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
                            $path = ManagedFiles::store($recording, 'assessment-responses', \Illuminate\Support\Str::uuid().'.'.$extension);
                            abort_unless($path, 500, 'The recording could not be saved.');
                            $files[] = $answers[$index] = $path;
                        }
                    } elseif (isset($data['answers'][$index])) {
                        $answers[$index] = $data['answers'][$index];
                    }
                }
                $result = AssessmentQuestions::result($locked->snapshot, $answers);
                $locked->update($result + ['answers' => $answers, 'submitted_at' => now()]);
                $eligibleForCertificate = !$locked->isSpecialized() || ($locked->snapshot['kind'] ?? 'exam') !== 'quiz'
                    || SpecializedExamAttempts::student($request->user());
                if ($eligibleForCertificate && CertificateAwards::assessment($locked) && $enrollment) {
                    CertificateAwards::course($enrollment->course, $enrollment);
                }
                if (array_filter($locked->snapshot['questions'], AssessmentQuestions::manual(...)) !== []) {
                    $route = ($locked->snapshot['kind'] ?? 'exam') === 'quiz' ? 'admin.quizzes-manager.edit' : 'admin.exams.edit';
                    WorkspaceNotifications::administrators('Assessment response ready to grade', $route, ['exam' => $locked->exam]);
                }
            });
        } catch (Throwable $error) {
            if ($files !== []) {
                ManagedFiles::disk()->delete($files);
            }
            throw $error;
        }
    }

    public function feedback(Request $request, ExamAttempt $attempt, ?CourseEnrollment $enrollment = null): JsonResponse
    {
        $this->authorizeAttempt($request, $attempt, $enrollment);
        $data = $request->validate(['question' => ['required', 'integer', 'min:0', 'max:199'], 'answer' => ['required', 'string', 'max:300']]);

        return DB::transaction(function () use ($request, $attempt, $enrollment, $data): JsonResponse {
            $locked = ExamAttempt::lockForUpdate()->findOrFail($attempt->id);
            $this->authorizeAttempt($request, $locked, $enrollment);
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

    public function recording(Request $request, ExamAttempt $attempt, int $question): Response
    {
        if (!AdminAccess::can($request->user(), 'learning')) {
            $this->authorizeAttempt($request, $attempt, $attempt->enrollment);
        }
        abort_unless($attempt->submitted_at && ($attempt->snapshot['questions'][$question]['format'] ?? null) === 'speaking', 404);
        $path = $attempt->answers[$question] ?? '';
        $types = ['mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'ogg' => 'audio/ogg', 'wav' => 'audio/wav', 'webm' => 'audio/webm'];
        $type = $types[pathinfo($path, PATHINFO_EXTENSION)] ?? null;
        abort_unless($type && str_starts_with($path, 'assessment-responses/'), 404);

        return ManagedFiles::response($request, $path, [
            'Content-Type' => $type, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ], seekable: true);
    }

    private function ensureOpen(ExamAttempt $attempt, ?CourseEnrollment $enrollment): void
    {
        abort_if($enrollment && $enrollment->fresh()?->status !== 'approved', 403);
        abort_if($attempt->submitted_at || !$attempt->expires_at->isFuture(), 409, 'This attempt has already ended.');
    }

    private function authorizeAttempt(Request $request, ExamAttempt $attempt, ?CourseEnrollment $enrollment = null): void
    {
        if ($request->routeIs('specialized-exams.*')) {
            abort_unless(SpecializedExamAttempts::owns($request, $attempt), 404);
        } elseif ($enrollment) {
            LearningAccess::course($request->user(), $enrollment);
            abort_unless($attempt->course_enrollment_id === $enrollment->id, 404);
        } else {
            abort_unless($attempt->course_enrollment_id === null && $attempt->student_id === $request->user()->id, 404);
        }
    }
}
