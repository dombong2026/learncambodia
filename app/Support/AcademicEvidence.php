<?php

namespace App\Support;

use App\Models\Course;
use App\Models\CourseEnrollment;

final class AcademicEvidence
{
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
