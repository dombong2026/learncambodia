<?php

namespace App\Support;

use App\Models\Course;
use App\Models\CourseEnrollment;

final class AcademicEvidence
{
    /**
     * MySQL JSON objects may canonicalize property order; SQLite preserves the
     * insertion order. Compare exact snapshot keys, types and values, not key order.
     */
    public static function matchesSnapshot(array $fresh, array $stored): bool
    {
        ksort($fresh, SORT_STRING);
        ksort($stored, SORT_STRING);

        return $fresh === $stored;
    }

    public static function summary(CourseEnrollment $enrollment): array
    {
        $course = Course::published()->whereKey($enrollment->course_id)->firstOrFail();

        return [
            'course_id' => $course->id,
            'course_revision' => $course->revision_number,
            'lesson_count' => $course->lessons()->count(),
            'self_reported_completed_lessons' => $enrollment->lessonProgress()
                ->whereNotNull('completed_at')->count(),
            'passed_practice_attempts' => $enrollment->attempts()
                ->where('passed', true)->count(),
            'graded_homework_submissions' => $enrollment->homeworkSubmissions()
                ->where('status','graded')->count(),
        ];
    }
}
