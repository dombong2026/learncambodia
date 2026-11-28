<?php

namespace App\Support;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\CourseQuiz;
use App\Models\User;
use Illuminate\Http\Request;

final class LearningAccess
{
    public static function course(User $student, CourseEnrollment $enrollment): Course
    {
        abort_unless($student->role === User::ROLE_STUDENT
            && $enrollment->student_id === $student->id, 404);
        abort_unless($enrollment->status === 'approved', 403);

        return Course::published()->whereKey($enrollment->course_id)->firstOrFail();
    }

    public static function lesson(Course $course, CourseLesson $lesson): void
    {
        abort_unless($lesson->module()
            ->where('course_id', $course->id)->exists(), 404);
    }

    public static function sourceLesson(Request $request, Course $course): ?CourseLesson
    {
        if (!$request->has('from_lesson')) {
            return null;
        }

        $lesson = CourseLesson::findOrFail($request->integer('from_lesson'));
        self::lesson($course, $lesson);

        return $lesson;
    }

    public static function curriculumProgress(Course $course, CourseEnrollment $enrollment): array
    {
        $course->load('modules.lessons:id,course_module_id,title,position,duration_minutes,quiz_questions');

        return $enrollment->lessonProgress()->whereNotNull('completed_at')
            ->whereIn('lesson_id', $course->modules->flatMap(fn ($module) => $module->lessons->modelKeys()))
            ->pluck('lesson_id')->all();
    }

    public static function quiz(Course $course, CourseQuiz $quiz): void
    {
        abort_unless($quiz->course_id === $course->id, 404);
    }
}
