<?php

namespace App\Support;

use App\Models\Course;
use App\Models\CourseInstructorAssignment;
use App\Models\User;

final class InstructorAccess
{
    public static function canTeach(User $instructor, Course $course): bool
    {
        return $instructor->role === User::ROLE_INSTRUCTOR
            && $course->status === 'published'
            && $course->published_at !== null
            && $course->published_at->lte(now())
            && CourseInstructorAssignment::query()
                ->where('course_id', $course->id)
                ->where('instructor_id', $instructor->id)
                ->where('status', 'active')->exists();
    }

    public static function check(User $instructor, Course $course): void
    {
        abort_unless(self::canTeach($instructor, $course), 403,
            'You are not currently assigned to teach this course.');
    }
}
