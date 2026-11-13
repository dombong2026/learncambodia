<?php

namespace App\Support;

use App\Models\AcademicEvidenceReview;
use App\Models\AppearanceRevision;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseHomeworkAssignment;
use App\Models\CourseInstructorAssignment;
use App\Models\HomeworkSubmission;
use App\Models\LessonProgress;
use App\Models\PracticeAttempt;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

final class DashboardInsights
{
    private static function metric(string $label, int $value, string $icon, string $url, string $detail): array
    {
        return compact('label', 'value', 'icon', 'url', 'detail');
    }

    public static function admin(): array
    {
        $pendingCourses = Course::where('status', 'pending_review')->count();
        $pendingEnrollments = CourseEnrollment::where('status', 'pending')->count();
        $pendingHomework = CourseHomeworkAssignment::where('status', 'pending_review')->count();
        $pendingEvidence = AcademicEvidenceReview::where('status', 'pending_review')->count();
        $pendingAppearance = AppearanceRevision::where('status', 'pending_review')->count();

        $queue = [
            ['label' => __('dashboard.course_reviews'), 'value' => $pendingCourses, 'url' => route('admin.reviews.index',['focus'=>'courses'])],
            ['label' => __('dashboard.enrollment_reviews'), 'value' => $pendingEnrollments, 'url' => route('admin.reviews.index',['focus'=>'enrollments'])],
            ['label' => __('dashboard.homework_reviews'), 'value' => $pendingHomework, 'url' => route('admin.homework.reviews')],
            ['label' => __('dashboard.appearance_reviews'), 'value' => $pendingAppearance, 'url' => route('admin.reviews.index',['focus'=>'appearance'])],
            ['label' => __('dashboard.academic_reviews'), 'value' => $pendingEvidence, 'url' => route('admin.academic.evidence')],
        ];

        // Count actual enrollment-request events; no attendance or payment estimates.
        $series = DB::table('course_enrollments')
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupByRaw('DATE(created_at)')
            ->pluck('total','day');

        $daily = [];
        foreach (range(6, 0) as $daysAgo) {
            $day = now()->subDays($daysAgo)->toDateString();
            $daily[] = ['date'=>$day,'count'=>(int) ($series[$day] ?? 0)];
        }

        return [
            'role'=>'admin','heading'=>__('dashboard.admin_heading'),
            'description'=>__('dashboard.admin_description'),
            'stats'=>[
                self::metric(__('dashboard.approved_enrollments'),
                    CourseEnrollment::where('status','approved')->count(),
                    'users',route('admin.reviews.index',['focus'=>'enrollments']),__('dashboard.real_record_count')),
                self::metric(__('dashboard.published_courses'),
                    Course::discoverable()->count(),
                    'book',route('admin.courses.index'),__('dashboard.public_catalogue')),
                self::metric(__('dashboard.instructor_accounts'),
                    User::where('role',User::ROLE_INSTRUCTOR)->count(),
                    'graduation',route('admin.instructors.index'),__('dashboard.provisioned_accounts')),
                self::metric(__('dashboard.pending_approvals'),
                    $pendingCourses+$pendingEnrollments+$pendingHomework+$pendingAppearance+$pendingEvidence,
                    'shield',route('admin.reviews.index'),__('dashboard.awaiting_review')),
                self::metric(__('dashboard.graded_homework'),
                    HomeworkSubmission::where('status','graded')->count(),
                    'activity',route('admin.academic.gradebook'),__('dashboard.internal_grades')),
            ],
            'reviewQueue'=>$queue,
            'enrollmentTrend'=>$daily,
        ];
    }

    public static function student(User $student): array
    {
        $id = $student->id;
        $scope = fn ($query) => $query->where('student_id', $id)->where('status','approved');

        $activeCourses = CourseEnrollment::query()
            ->where('student_id',$id)->where('status','approved')
            ->whereHas('course', fn ($query) => $query->published())
            ->with(['course' => fn ($query) => $query->withCount('lessons')])
            ->withCount(['lessonProgress as completed_lessons_count' =>
                fn ($query) => $query->whereNotNull('completed_at')])
            ->orderByDesc('id')->limit(4)->get();

        $upcoming = CourseHomeworkAssignment::published()
            ->whereNotNull('due_at')->where('due_at','>=',now())
            ->whereHas('course', fn ($query) => $query->published()
                ->whereHas('enrollments',$scope))
            ->whereDoesntHave('submissions', fn ($submissions) =>
                $submissions->whereHas('enrollment',fn ($enrollments) => $enrollments
                    ->where('student_id',$id))
                    ->whereIn('status',['submitted','graded']))
            ->with('course')->orderBy('due_at')->limit(6)->get();

        $practice = PracticeAttempt::query()
            ->whereHas('enrollment',fn($query)=>$query->where('student_id',$id))
            ->with('quiz.course.category')
            ->orderByDesc('submitted_at')->limit(50)->get();

        $performance = $practice->groupBy(fn($attempt) =>
            $attempt->quiz?->course?->category?->name ?? __('dashboard.uncategorized'))
            ->map(fn($attempts) => [
                'value'=>(int) round($attempts->avg('score_percent')),
                'sample'=>$attempts->count(),
            ])->sortByDesc('sample')->take(5)->all();

        $completed = LessonProgress::query()
            ->whereNotNull('completed_at')->where('completed_at','>=',now()->subDays(35)->startOfDay())
            ->whereHas('enrollment', $scope)->pluck('completed_at');
        $attempted = PracticeAttempt::query()
            ->where('submitted_at','>=',now()->subDays(35)->startOfDay())
            ->whereHas('enrollment',$scope)->pluck('submitted_at');
        $submitted = HomeworkSubmission::query()
            ->where('submitted_at','>=',now()->subDays(35)->startOfDay())
            ->whereHas('enrollment',$scope)->pluck('submitted_at');
        $dates = $completed->concat($attempted)->concat($submitted)
            ->map(fn($date)=>Carbon::parse($date)->toDateString())->unique()->flip();
        $today = now()->startOfDay();
        $streak = 0;
        $cursor = $dates->has($today->toDateString()) ? $today : $today->copy()->subDay();
        while ($dates->has($cursor->toDateString()) && $streak < 35) {
            $streak++;
            $cursor->subDay();
        }

        return [
            'role'=>'student','heading'=>__('dashboard.student_heading'),
            'description'=>__('dashboard.student_description'),
            'stats'=>[
                self::metric(__('dashboard.active_courses'),
                    CourseEnrollment::where('student_id',$id)->where('status','approved')
                        ->whereHas('course',fn($query)=>$query->published())->count(),
                    'book',route('account.enrollments.index'),__('dashboard.approved_courses')),
                self::metric(__('dashboard.lessons_completed'),
                    LessonProgress::whereNotNull('completed_at')
                        ->whereHas('enrollment',$scope)->count(),
                    'activity',route('account.progress.index'),__('dashboard.self_reported')),
                self::metric(__('dashboard.graded_homework'),
                    HomeworkSubmission::where('status','graded')
                        ->whereHas('enrollment',$scope)->count(),
                    'graduation',route('account.homework.submissions'),__('dashboard.internal_grades')),
                self::metric(__('dashboard.practice_attempts'),
                    PracticeAttempt::whereHas('enrollment',$scope)->count(),
                    'layers',route('account.practice.history'),__('dashboard.practice_only')),
                self::metric(__('dashboard.study_streak'),
                    $streak,'flame',route('account.progress.index'),__('dashboard.streak_window')),
            ],
            'activeCourses'=>$activeCourses,
            'upcoming'=>$upcoming,
            'performance'=>$performance,
        ];
    }

    public static function instructor(User $instructor): array
    {
        $id = $instructor->id;
        $assigned = fn ($query) => $query->published()->whereHas('instructorAssignments',
            fn ($assignments) => $assignments->where('instructor_id',$id)
                ->where('status','active'));
        $pending = fn ($query) => $query->where('status','published')
            ->whereHas('course',$assigned);

        $queue = HomeworkSubmission::query()
            ->where('status','submitted')->whereHas('assignment',$pending)
            ->with(['assignment:id,title,course_id','enrollment.student:id,name'])
            ->orderBy('submitted_at')->limit(5)->get();

        $due = CourseHomeworkAssignment::published()
            ->whereHas('course',$assigned)
            ->whereBetween('due_at',[now(),now()->addDays(7)])
            ->with('course:id,title')->orderBy('due_at')->limit(5)->get();

        return [
            'role'=>'instructor','heading'=>__('dashboard.instructor_heading'),
            'description'=>__('dashboard.instructor_description'),
            'stats'=>[
                self::metric(__('dashboard.assigned_courses'),
                    CourseInstructorAssignment::where('instructor_id',$id)
                        ->where('status','active')->whereHas('course',fn($q)=>$q->published())->count(),
                    'book',route('instructor.courses.index'),__('dashboard.assigned_access')),
                self::metric(__('dashboard.active_students'),
                    CourseEnrollment::where('status','approved')->whereHas('course',$assigned)
                        ->distinct('student_id')->count('student_id'),
                    'users',route('instructor.courses.index'),__('dashboard.distinct_learners')),
                self::metric(__('dashboard.awaiting_grading'),
                    HomeworkSubmission::where('status','submitted')
                        ->whereHas('assignment',$pending)->count(),
                    'pen',route('instructor.grading.index'),__('dashboard.submitted_work')),
                self::metric(__('dashboard.grades_recorded'),
                    HomeworkSubmission::where('status','graded')
                        ->whereHas('assignment',$pending)->count(),
                    'graduation',route('instructor.grading.index'),__('dashboard.internal_grades')),
                self::metric(__('dashboard.due_soon'),
                    CourseHomeworkAssignment::published()
                        ->whereHas('course',$assigned)
                        ->whereBetween('due_at',[now(),now()->addDays(7)])->count(),
                    'activity',route('instructor.homework.index'),__('dashboard.next_seven_days')),
            ],
            'gradingQueue'=>$queue,
            'upcoming'=>$due,
        ];
    }

    public static function developer(User $developer): array
    {
        $tokens = $developer->tokens();
        $active = (clone $tokens)->where('expires_at','>',now())->count();
        $expired = (clone $tokens)->where('expires_at','<=',now())->count();
        $apiRoutes = collect(Route::getRoutes())->filter(fn($route) =>
            str_starts_with($route->uri(),'api/v1/') && in_array('GET',$route->methods(),true))->count();

        return [
            'role'=>'developer','heading'=>__('dashboard.developer_heading'),
            'description'=>__('dashboard.developer_description'),
            'stats'=>[
                self::metric(__('dashboard.active_tokens'),$active,
                    'key',route('developer.tokens.index'),__('dashboard.unexpired_tokens')),
                self::metric(__('dashboard.expired_tokens'),$expired,
                    'shield',route('developer.tokens.index'),__('dashboard.expired_records')),
                self::metric(__('dashboard.token_slots'),max(0,10-$active),
                    'activity',route('developer.tokens.index'),__('dashboard.maximum_ten')),
                self::metric(__('dashboard.read_endpoints'),$apiRoutes,
                    'code',route('developer.apis'),__('dashboard.registered_routes')),
            ],
        ];
    }
}
