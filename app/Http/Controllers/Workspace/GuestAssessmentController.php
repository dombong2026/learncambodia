<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\CourseExam;
use App\Models\EditorMedia;
use App\Models\ExamAttempt;
use App\Support\AssessmentQuestions;
use App\Support\GuestAssessment;
use App\Support\ManagedFiles;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class GuestAssessmentController extends Controller
{
    public function start(Request $request, CourseExam $exam): RedirectResponse
    {
        abort_unless(GuestAssessment::allowed($exam), 404);
        $identity = GuestAssessment::identity($request, true);
        abort_unless($identity !== null, 404);
        $attempt = DB::transaction(function () use ($exam, $identity): ExamAttempt {
            $exam = CourseExam::lockForUpdate()->findOrFail($exam->id);
            abort_unless(GuestAssessment::allowed($exam), 404);
            $history = ExamAttempt::where('course_exam_id', $exam->id)
                ->whereNull('student_id')->whereNull('course_enrollment_id')
                ->where('guest_identity_hash', $identity);
            $active = (clone $history)->whereNull('submitted_at')->where('expires_at', '>', now())->first();
            if ($active) {
                return $active;
            }
            abort_if($history->count() >= $exam->attempt_limit, 409, 'All attempts for this browser session have been used.');
            $questions = $exam->questions;
            if ($exam->shuffle) {
                shuffle($questions);
            }
            return ExamAttempt::create([
                'course_exam_id' => $exam->id, 'course_enrollment_id' => null, 'student_id' => null,
                'guest_identity_hash' => $identity,
                'snapshot' => [
                    'title' => $exam->title, 'instructions_html' => $exam->instructions_html,
                    'questions' => $questions, 'passing_percent' => $exam->passing_percent,
                    'kind' => $exam->kind, 'gamified' => $exam->gamified,
                    // Do not reveal correct answers through anonymous feedback requests.
                    'live_feedback' => false,
                ],
                'expires_at' => now()->addMinutes($exam->time_limit),
            ]);
        });
        return redirect()->route('guest-assessments.show', $attempt)->header('Cache-Control', 'private, no-store');
    }

    private function authorize(Request $request, ExamAttempt $attempt): CourseExam
    {
        abort_unless(GuestAssessment::owns($request, $attempt), 404);
        $exam = $attempt->exam;
        abort_unless($exam && GuestAssessment::allowed($exam), 404);
        return $exam;
    }

    public function show(Request $request, ExamAttempt $attempt): Response
    {
        $exam = $this->authorize($request, $attempt);
        return response()->view('learning.guest-assessment-attempt', compact('exam', 'attempt'))
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function submit(Request $request, ExamAttempt $attempt): RedirectResponse
    {
        $exam = $this->authorize($request, $attempt);
        abort_if($request->has('recordings') || $request->allFiles() !== [], 422);
        $data = $request->validate(['answers' => ['sometimes', 'array', 'max:200'],
            'answers.*' => ['nullable', 'string', 'max:8000']]);
        DB::transaction(function () use ($request, $exam, $attempt, $data): void {
            $current = CourseExam::lockForUpdate()->findOrFail($exam->id);
            abort_unless(GuestAssessment::allowed($current), 404);
            $locked = ExamAttempt::lockForUpdate()->findOrFail($attempt->id);
            abort_unless(GuestAssessment::owns($request, $locked), 404);
            abort_if($locked->submitted_at || !$locked->expires_at->isFuture(), 409, 'This attempt has ended.');
            $answers = [];
            foreach ($data['answers'] ?? [] as $index => $answer) {
                $question = $locked->snapshot['questions'][$index] ?? null;
                if (!$question || $answer === null || $answer === '') {
                    continue;
                }
                $format = $question['format'] ?? 'choice';
                $valid = match ($format) {
                    'choice', 'reading', 'listening' => in_array($answer, ['a','b','c','d'], true) && filled($question[$answer] ?? null),
                    'boolean' => in_array($answer, ['true','false'], true),
                    'likert' => in_array($answer, ['1','2','3','4','5'], true),
                    'order' => preg_match('/\A[a-d](?:,[a-d]){1,3}\z/D', $answer) === 1,
                    'fill', 'short' => mb_strlen($answer) <= 300,
                    'writing' => true,
                    default => false,
                };
                if (!$valid) {
                    throw ValidationException::withMessages(["answers.$index" => 'Invalid answer format.']);
                }
                $answers[$index] = $answer;
            }
            $locked->update(AssessmentQuestions::result($locked->snapshot, $answers)
                + ['answers' => $answers, 'submitted_at' => now()]);
            // Native administrator grading queue includes submitted writing responses.
            // No anonymous notifications or account creation.
        });
        return redirect()->route('guest-assessments.show', $attempt)->header('Cache-Control', 'private, no-store');
    }

    public function audio(Request $request, ExamAttempt $attempt, EditorMedia $media): Response
    {
        $exam = $this->authorize($request, $attempt);
        abort_unless(!$attempt->submitted_at && $attempt->expires_at->isFuture()
            && $media->context === 'exam'
            && in_array($media->mime, ['audio/wav', 'audio/mpeg', 'audio/ogg', 'audio/mp4', 'audio/webm'], true)
            && $exam->media()->whereKey($media->id)->exists()
            && collect($attempt->snapshot['questions'])->contains(fn ($q) => ($q['format'] ?? null) === 'listening'
                && (int) ($q['media_id'] ?? 0) === (int) $media->id), 404);
        return ManagedFiles::response($request, $media->path, [
            'Content-Type' => $media->mime, 'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Referrer-Policy' => 'no-referrer',
        ], seekable: true);
    }
}
