<?php

namespace App\Support;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseHomeworkAssignment;
use App\Models\CourseInstructorAssignment;
use App\Models\HomeworkSubmission;
use App\Models\User;

final class DashboardShowcase
{
    /**
     * Reference-inspired overview cards, strictly derived from authorized
     * records. Zero denominators intentionally produce null, not a made-up 0%.
     */
    public static function forRole(string $role, array $data, ?User $actor = null): array
    {
        return match ($role) {
            'admin' => self::admin($data),
            'student' => self::student($data, $actor),
            'instructor' => self::instructor($data, $actor),
            'developer' => self::developer($data),
            default => [],
        };
    }

    public static function percent(int $part, int $total): ?int
    {
        return $total > 0 ? (int) min(100, max(0, round($part * 100 / $total))) : null;
    }

    private static function ring(string $label, ?int $percent, string $caption, string $url): array
    {
        return compact('label','percent','caption','url');
    }

    private static function action(string $label, int $count, string $url, string $icon): array
    {
        return compact('label','count','url','icon');
    }

    private static function entry(string $label, string $sub, string $when, string $url, string $urgency='normal'): array
    {
        return compact('label','sub','when','url','urgency');
    }

    private static function admin(array $data): array
    {
        $approved = CourseEnrollment::where('status', 'approved')->count();
        $total = CourseEnrollment::count();
        $published = Course::discoverable()->count();
        $all = Course::count();
        $items = [];
        foreach (array_slice($data['reviewQueue'] ?? [], 0, 4) as $queue) {
            $items[] = self::entry($queue['label'],
                __('dashboard.awaiting_review'), DashboardNumber::compact($queue['value']),
                $queue['url'], $queue['value'] > 0 ? 'soon' : 'normal');
        }
        return [
            'greeting' => __('dashboard.hello_admin'),
            'status' => __('dashboard.pending_approvals'),
            'status_count' => $data['stats'][3]['value'],
            'feature_title' => __('dashboard.feature_admin'),
            'feature_description' => __('dashboard.feature_detail'),
            'feature_url' => route('admin.learning.report'),
            'feature_link' => __('dashboard.look_at'),
            'rings' => [
                self::ring(__('dashboard.approved_rate'),
                    self::percent($approved,$total),
                    DashboardNumber::compact($approved).' / '.DashboardNumber::compact($total),
                    route('admin.reviews.index',['focus'=>'enrollments'])),
                self::ring(__('dashboard.published_rate'),
                    self::percent($published,$all),
                    DashboardNumber::compact($published).' / '.DashboardNumber::compact($all),
                    route('admin.courses.index')),
            ],
            'timeline_title' => __('dashboard.review_queue'),
            'timeline' => $items,
            'timeline_empty' => __('dashboard.queue_empty'),
            'actions_title' => __('dashboard.queue_title'),
            'actions' => [
                self::action(__('dashboard.course_reviews'),
                    (int) ($data['reviewQueue'][0]['value'] ?? 0),
                    route('admin.reviews.index',['focus'=>'courses']), 'book-open'),
                self::action(__('dashboard.homework_reviews'),
                    (int) ($data['reviewQueue'][2]['value'] ?? 0),
                    route('admin.homework.reviews'),'clipboard-check'),
                self::action(__('dashboard.academic_reviews'),
                    (int) ($data['reviewQueue'][4]['value'] ?? 0),
                    route('admin.academic.evidence'),'badge-check'),
            ],
        ];
    }

    private static function student(array $data, ?User $actor): array
    {
        $courses = $data['activeCourses'];
        $rings = [];
        foreach ($courses->take(2) as $enrollment) {
            $total = max(0, (int) ($enrollment->course?->lessons_count ?? 0));
            $complete = min($total, (int) ($enrollment->completed_lessons_count ?? 0));
            $rings[] = self::ring(
                $enrollment->course?->title ?? __('dashboard.no_course_current'),
                self::percent($complete, $total),
                $complete.' / '.$total.' '.__('dashboard.lessons_completed'),
                route('account.enrollments.show',$enrollment)
            );
        }

        $schedule = [];
        foreach ($data['upcoming'] as $assignment) {
            $date = $assignment->due_at;
            $schedule[] = self::entry($assignment->title,
                $assignment->course?->title ?? '',
                $date->format('M j, H:i'),
                route('account.homework.show',$assignment),
                $date->isToday() ? 'urgent' : ($date->lte(now()->addDays(7)) ? 'soon' : 'normal'));
        }

        return [
            'greeting' => __('dashboard.hello_student',['name'=>$actor?->name ?? __('dashboard.student_heading')]),
            'status' => __('dashboard.study_streak'),
            'status_count' => $data['stats'][4]['value'],
            'feature_title' => __('dashboard.feature_student'),
            'feature_description' => __('dashboard.self_reported'),
            'feature_url' => route('account.progress.index'),
            'feature_link' => __('dashboard.resume_learning'),
            'rings' => $rings,
            'timeline_title' => __('dashboard.student_planner'),
            'timeline' => array_slice($schedule,0,4),
            'timeline_empty' => __('dashboard.no_deadlines'),
            'actions_title' => __('dashboard.queue_title'),
            'actions' => [
                self::action(__('dashboard.active_courses'),
                    $data['stats'][0]['value'],route('account.enrollments.index'),'book-open'),
                self::action(__('dashboard.graded_homework'),
                    $data['stats'][2]['value'],route('account.homework.submissions'),'clipboard-check'),
                self::action(__('dashboard.practice_attempts'),
                    $data['stats'][3]['value'],route('account.practice.index'),'brain'),
            ],
        ];
    }

    private static function instructor(array $data, ?User $actor): array
    {
        $id = (int) $actor?->id;
        $scope = fn ($q) => $q->published()->whereHas('instructorAssignments',
            fn ($t) => $t->where('instructor_id',$id)->where('status','active'));
        $assignmentScope = fn ($q) => $q->where('status','published')->whereHas('course',$scope);
        $graded = HomeworkSubmission::query()->where('status','graded')
            ->whereHas('assignment',$assignmentScope)->count();
        $submitted = HomeworkSubmission::query()->where('status','submitted')
            ->whereHas('assignment',$assignmentScope)->count();
        $returned = HomeworkSubmission::query()->where('status','returned')
            ->whereHas('assignment',$assignmentScope)->count();
        $assigned = CourseInstructorAssignment::query()->where('instructor_id',$id)
            ->where('status','active')->whereHas('course',fn ($q)=>$q->published())->count();
        $coursesWithWork = CourseHomeworkAssignment::published()
            ->whereHas('course',$scope)->distinct('course_id')->count('course_id');

        $items = [];
        foreach ($data['gradingQueue'] as $submission) {
            $items[] = self::entry($submission->assignment?->title ?? '',
                __('dashboard.awaiting_grading'),
                $submission->submitted_at?->format('M j, H:i') ?? '',
                route('instructor.grading.show',$submission),'soon');
        }

        return [
            'greeting' => __('dashboard.hello_instructor'),
            'status' => __('dashboard.assigned_courses'),
            'status_count' => $assigned,
            'feature_title' => __('dashboard.feature_instructor'),
            'feature_description' => __('dashboard.feature_detail'),
            'feature_url' => route('instructor.courses.index'),
            'feature_link' => __('dashboard.go_to_courses'),
            'rings' => [
                self::ring(__('dashboard.grading_rate'),
                    self::percent($graded,$graded+$submitted+$returned),
                    DashboardNumber::compact($graded).' / '.DashboardNumber::compact($graded+$submitted+$returned),
                    route('instructor.grading.index')),
                self::ring(__('dashboard.my_teaching'),
                    self::percent($coursesWithWork,$assigned),
                    DashboardNumber::compact($coursesWithWork).' / '.DashboardNumber::compact($assigned),
                    route('instructor.homework.index')),
            ],
            'timeline_title' => __('dashboard.grading_queue'),
            'timeline' => array_slice($items,0,4),
            'timeline_empty' => __('dashboard.no_grading'),
            'actions_title' => __('dashboard.queue_title'),
            'actions' => [
                self::action(__('dashboard.awaiting_grading'),$submitted,route('instructor.grading.index'),'clipboard-list'),
                self::action(__('dashboard.graded_homework'),$graded,route('instructor.grading.index'),'chart-line'),
                self::action(__('dashboard.due_soon'),$data['stats'][4]['value'],route('instructor.homework.index'),'calendar'),
            ],
        ];
    }

    private static function developer(array $data): array
    {
        $active = $data['stats'][0]['value'];
        $expired = $data['stats'][1]['value'];
        return [
            'greeting'=>__('dashboard.hello_developer'),
            'status'=>__('dashboard.active_tokens'),
            'status_count'=>$active,
            'feature_title'=>__('dashboard.feature_developer'),
            'feature_description'=>__('dashboard.feature_detail'),
            'feature_url'=>route('developer.tokens.index'),
            'feature_link'=>__('dashboard.go_to_tokens'),
            'rings'=>[
                self::ring(__('dashboard.token_capacity'),self::percent($active,10),
                    $active.' / 10',route('developer.tokens.index')),
                self::ring(__('dashboard.read_endpoints'),$data['stats'][3]['value'] > 0 ? 100 : null,
                    DashboardNumber::compact($data['stats'][3]['value']).' GET',
                    route('developer.apis')),
            ],
            'timeline_title'=>__('dashboard.my_operations'),
            'timeline'=>[
                self::entry(__('dashboard.active_tokens'),__('dashboard.unexpired_tokens'),
                    DashboardNumber::compact($active),route('developer.tokens.index')),
                self::entry(__('dashboard.expired_tokens'),__('dashboard.expired_records'),
                    DashboardNumber::compact($expired),route('developer.tokens.index')),
                self::entry(__('dashboard.read_endpoints'),__('dashboard.registered_routes'),
                    DashboardNumber::compact($data['stats'][3]['value']),route('developer.apis')),
            ],
            'timeline_empty'=>__('dashboard.queue_empty'),
            'actions_title'=>__('dashboard.queue_title'),
            'actions'=>[
                self::action(__('dashboard.active_tokens'),$active,route('developer.tokens.index'),'key-round'),
                self::action(__('dashboard.token_slots'),$data['stats'][2]['value'],route('developer.tokens.index'),'gauge'),
                self::action(__('dashboard.read_endpoints'),$data['stats'][3]['value'],route('developer.apis'),'terminal'),
            ],
        ];
    }
}
