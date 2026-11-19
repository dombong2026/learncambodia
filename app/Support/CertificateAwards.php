<?php

namespace App\Support;

use App\Models\CertificateTemplate;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\ExamAttempt;
use App\Models\IssuedCertificate;
use App\Models\PracticeAttempt;
use App\Models\User;
use Illuminate\Support\Str;

final class CertificateAwards
{
    public static function courseEligible(Course $course, CourseEnrollment $enrollment): bool
    {
        $lessonCount = $course->lessons()->count();
        $completed = $enrollment->lessonProgress()->whereNotNull('completed_at')
            ->whereIn('lesson_id', $course->lessons()->select('course_lessons.id'))->count();
        $required = $course->exams()->where('published', true)->pluck('id');
        $passed = $enrollment->examAttempts()->whereIn('course_exam_id', $required)
            ->whereNotNull('submitted_at')->where('passed', true)->distinct()->count('course_exam_id');

        return $enrollment->status === 'approved' && $lessonCount > 0 && $completed === $lessonCount
            && $passed === $required->count();
    }

    // Callers hold the enrollment lock while recording the qualifying progress or final grade.
    public static function course(Course $course, CourseEnrollment $enrollment): ?IssuedCertificate
    {
        if (!Course::published()->whereKey($course->id)->exists() || !self::courseEligible($course, $enrollment)) {
            return null;
        }

        // Existing certificates, including revoked ones, count so automatic issuance cannot replace their status.
        $existing = IssuedCertificate::where('course_enrollment_id', $enrollment->id)->orderBy('id')->first();
        if ($existing) {
            return $existing;
        }

        $template = $course->certificateTemplates()->whereHas('type', fn ($query) => $query->where('kind', 'course'))
            ->orderBy('certificate_templates.id')->first()
            ?? $course->certificateTemplates()->whereHas('type', fn ($query) => $query->whereNull('kind'))
                ->orderBy('certificate_templates.id')->first()
            ?? self::defaultTemplate('course');
        $student = $enrollment->student;

        return self::issue($template, $student, 'course', "course:{$enrollment->id}", $course->title, null, $enrollment);
    }

    public static function assessmentKey(ExamAttempt $attempt, int $studentId): string
    {
        return "assessment:{$attempt->course_exam_id}:{$studentId}";
    }

    public static function practiceKey(PracticeAttempt $attempt, int $studentId): string
    {
        return "practice:{$attempt->course_quiz_id}:{$studentId}";
    }

    public static function assessment(ExamAttempt $attempt): ?IssuedCertificate
    {
        if (!$attempt->submitted_at || $attempt->passed !== true) {
            return null;
        }

        $enrollment = $attempt->enrollment;
        $student = $enrollment ? $enrollment->student : $attempt->student;
        $exam = $attempt->exam;
        if (!$student || !$exam || ($enrollment && $enrollment->status !== 'approved')) {
            return null;
        }

        $kind = ($attempt->snapshot['kind'] ?? $exam->kind) === 'quiz' ? 'quiz' : 'exam';

        $template = CertificateTemplate::whereKey($exam->certificate_template_id)
            ->whereHas('type', fn ($query) => $query->where('kind', $kind))->first()
            ?? self::defaultTemplate($kind);

        return self::issue($template, $student, $kind,
            self::assessmentKey($attempt, $student->id), $enrollment?->course?->title ?? '', $attempt->snapshot['title']);
    }

    public static function practice(PracticeAttempt $attempt): ?IssuedCertificate
    {
        if (!$attempt->submitted_at || $attempt->passed !== true || $attempt->enrollment->status !== 'approved') {
            return null;
        }

        $student = $attempt->enrollment->student;

        return self::issue(self::defaultTemplate('quiz'), $student, 'quiz',
            self::practiceKey($attempt, $student->id), $attempt->enrollment->course->title, $attempt->quiz->title);
    }

    private static function defaultTemplate(string $kind): CertificateTemplate
    {
        return CertificateTemplate::where('default_kind', $kind)->firstOrFail();
    }

    private static function issue(CertificateTemplate $template, User $student, string $kind, string $key,
        string $course, ?string $assessment, ?CourseEnrollment $enrollment = null): IssuedCertificate
    {
        $certificate = IssuedCertificate::firstOrCreate(['award_key' => $key], [
            'student_id' => $student->id,
            'course_enrollment_id' => $enrollment?->id,
            'certificate_template_id' => $template->id,
            'code' => (string) Str::uuid(),
            'snapshot' => $template->only('heading', 'body', 'issuer', 'accent', 'orientation', 'design') + [
                'type' => ucfirst($kind), 'kind' => $kind,
                'learner' => $student->name, 'course' => $course, 'assessment' => $assessment,
            ],
        ]);
        if ($certificate->wasRecentlyCreated) {
            WorkspaceNotifications::send($student->id, ucfirst($kind).' certificate issued', 'certificates.show', ['certificate' => $certificate->code]);
        }

        return $certificate;
    }
}
