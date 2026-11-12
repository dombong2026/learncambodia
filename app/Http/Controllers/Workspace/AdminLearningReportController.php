<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\LessonProgress;
use App\Models\PracticeAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminLearningReportController extends Controller
{
    public function index(): View
    {
        $courses = Course::query()->with('category')
            ->withCount([
                'lessons', 'quizzes',
                'enrollments as approved_enrollments_count' =>
                    fn ($query) => $query->where('status', 'approved'),
                'enrollments as pending_enrollments_count' =>
                    fn ($query) => $query->where('status', 'pending'),
            ])->orderByDesc('id')->paginate(10);

        $ids = $courses->getCollection()->modelKeys();
        $completedByCourse = DB::table('lesson_progress')
            ->join('course_enrollments', 'course_enrollments.id', '=', 'lesson_progress.enrollment_id')
            ->whereIn('course_enrollments.course_id', $ids)
            ->whereNotNull('lesson_progress.completed_at')
            ->where('course_enrollments.status', 'approved')
            ->groupBy('course_enrollments.course_id')
            ->selectRaw('course_enrollments.course_id, count(*) as completed')
            ->pluck('completed', 'course_id');

        $attemptsByCourse = DB::table('practice_attempts')
            ->join('course_quizzes', 'course_quizzes.id', '=', 'practice_attempts.course_quiz_id')
            ->whereIn('course_quizzes.course_id', $ids)
            ->groupBy('course_quizzes.course_id')
            ->selectRaw('course_quizzes.course_id, count(*) as attempts')
            ->pluck('attempts', 'course_id');

        return view('workspace.admin.learning-report', [
            'role' => 'admin', 'courses' => $courses,
            'completedByCourse' => $completedByCourse,
            'attemptsByCourse' => $attemptsByCourse,
            'totals' => [
                ['label' => 'Approved enrollments',
                    'value' => CourseEnrollment::where('status', 'approved')->count()],
                ['label' => 'Completed lesson records',
                    'value' => LessonProgress::whereNotNull('completed_at')->count()],
                ['label' => 'Practice submissions', 'value' => PracticeAttempt::count()],
            ],
        ]);
    }
}
