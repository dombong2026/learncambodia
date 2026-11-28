<?php

namespace App\Support;

use App\Models\CourseExam;
use App\Models\ExamAttempt;
use Illuminate\Http\Request;

final class GuestAssessment
{
    private const SESSION_KEY = 'guest_assessment_secret';

    public static function enabled(): bool
    {
        if (app()->environment('production')) {
            return config('guest-assessment.production_accepted') === true
                && config('guest-assessment.production_enabled') === true
                && config('guest-assessment.rehearsal') === false
                && in_array(config('session.driver'), ['database', 'file', 'redis'], true);
        }
        return app()->environment(['local', 'testing', 'staging'])
            && config('guest-assessment.rehearsal') === true;
    }

    public static function compatible(?int $courseId, ?array $pricing, array $questions): bool
    {
        return $courseId === null && $pricing === null && $questions !== []
            && !in_array('speaking', array_column($questions, 'format'), true);
    }

    public static function allowed(CourseExam $exam): bool
    {
        return self::enabled() && $exam->viewer_mode === 'default'
            && $exam->guest_enabled && $exam->published
            && !$exam->deactivated_at && in_array($exam->kind, ['quiz', 'exam'], true)
            && self::compatible($exam->course_id, $exam->pricing, $exam->questions ?? []);
    }

    public static function identity(Request $request, bool $create = false): ?string
    {
        if ($request->user() || !$request->hasSession()) {
            return null;
        }
        $secret = $request->session()->get(self::SESSION_KEY);
        if (!is_string($secret) || !preg_match('/\A[a-f0-9]{64}\z/D', $secret)) {
            if (!$create) {
                return null;
            }
            $secret = bin2hex(random_bytes(32));
            $request->session()->put(self::SESSION_KEY, $secret);
        }
        return hash('sha256', $secret);
    }

    public static function owns(Request $request, ExamAttempt $attempt): bool
    {
        $identity = self::identity($request);
        return self::enabled() && $identity !== null && $attempt->student_id === null
            && $attempt->course_enrollment_id === null && is_string($attempt->guest_identity_hash)
            && hash_equals($attempt->guest_identity_hash, $identity);
    }
}
